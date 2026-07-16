<?php

use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
});

describe('Inventory pricing', function () {
    it('creates a price list and sets a price', function (): void {
        [, $token, $companyId] = inventoryActor();
        $uom = createUnit($token, $companyId, 'pcs');
        $productId = createProduct($token, $companyId, [
            'name' => 'Coffee',
            'base_uom_id' => $uom,
            'variants' => [['sku' => 'PRC-1']],
        ]);
        $variantId = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/products/{$productId}")
            ->json('data.product.variants.0.id');

        // Create price list
        $priceListId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/inventory/price-lists', [
                'name' => 'Default Price List',
                'is_default' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.price_list.name', 'Default Price List')
            ->json('data.price_list.id');

        // Set a price
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->putJson("/api/v1/inventory/price-lists/{$priceListId}/prices", [
                'product_variant_id' => $variantId,
                'price' => 25000,
                'effective_from' => '2026-01-01',
            ])
            ->assertSuccessful();

        $this->assertDatabaseHas('prices', [
            'price_list_id' => $priceListId,
            'product_variant_id' => $variantId,
            'price' => '25000.0000',
        ]);
    });

    it('resolves effective-dated price correctly', function (): void {
        [, $token, $companyId] = inventoryActor();
        $uom = createUnit($token, $companyId, 'pcs');
        $productId = createProduct($token, $companyId, [
            'name' => 'Tea',
            'base_uom_id' => $uom,
            'variants' => [['sku' => 'PRC-2']],
        ]);
        $variantId = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/products/{$productId}")
            ->json('data.product.variants.0.id');

        $priceListId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/inventory/price-lists', ['name' => 'Retail'])
            ->assertCreated()
            ->json('data.price_list.id');

        // Price A: 25000 from Jan
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->putJson("/api/v1/inventory/price-lists/{$priceListId}/prices", [
                'product_variant_id' => $variantId,
                'price' => 25000,
                'effective_from' => '2026-01-01',
            ])->assertSuccessful();

        // Price B: 30000 from June
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->putJson("/api/v1/inventory/price-lists/{$priceListId}/prices", [
                'product_variant_id' => $variantId,
                'price' => 30000,
                'effective_from' => '2026-06-01',
            ])->assertSuccessful();

        // Resolve on March → should be 25000
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/products/{$productId}/variants/{$variantId}/price?on=2026-03-01")
            ->assertSuccessful()
            ->assertJsonPath('data.price', '25000.0000');

        // Resolve on July → should be 30000
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/products/{$productId}/variants/{$variantId}/price?on=2026-07-01")
            ->assertSuccessful()
            ->assertJsonPath('data.price', '30000.0000');
    });

    it('batch-resolves effective prices for all company variants', function (): void {
        [, $token, $companyId] = inventoryActor();
        $uom = createUnit($token, $companyId, 'pcs');
        $productId = createProduct($token, $companyId, [
            'name' => 'Juice',
            'base_uom_id' => $uom,
            'variants' => [['sku' => 'BATCH-1'], ['sku' => 'BATCH-2']],
        ]);
        $variants = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/products/{$productId}")
            ->json('data.product.variants');
        $variantA = $variants[0]['id'];

        $priceListId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/inventory/price-lists', ['name' => 'Retail'])
            ->assertCreated()
            ->json('data.price_list.id');

        // Variant A: 10000 from Jan, 12000 from June. Variant B stays unpriced.
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->putJson("/api/v1/inventory/price-lists/{$priceListId}/prices", [
                'product_variant_id' => $variantA,
                'price' => 10000,
                'effective_from' => '2026-01-01',
            ])->assertSuccessful();
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->putJson("/api/v1/inventory/price-lists/{$priceListId}/prices", [
                'product_variant_id' => $variantA,
                'price' => 12000,
                'effective_from' => '2026-06-01',
            ])->assertSuccessful();

        // On March only the Jan price is effective; the unpriced variant is absent.
        $march = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/inventory/prices/resolve?on=2026-03-01')
            ->assertSuccessful()
            ->json('data.prices');
        expect($march)->toHaveCount(1);
        expect($march[0]['product_variant_id'])->toBe($variantA);
        expect($march[0]['price'])->toBe('10000.0000');

        // On July the June price wins.
        $july = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/inventory/prices/resolve?on=2026-07-01')
            ->assertSuccessful()
            ->json('data.prices');
        expect(collect($july)->firstWhere('product_variant_id', $variantA)['price'])->toBe('12000.0000');
    });

    it('lists price rows for a price list', function (): void {
        [, $token, $companyId] = inventoryActor();
        $uom = createUnit($token, $companyId, 'pcs');
        $productId = createProduct($token, $companyId, [
            'name' => 'Cocoa',
            'base_uom_id' => $uom,
            'variants' => [['sku' => 'PRC-3']],
        ]);
        $variantId = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/products/{$productId}")
            ->json('data.product.variants.0.id');

        $priceListId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/inventory/price-lists', ['name' => 'Retail'])
            ->assertCreated()
            ->json('data.price_list.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->putJson("/api/v1/inventory/price-lists/{$priceListId}/prices", [
                'product_variant_id' => $variantId,
                'price' => 25000,
                'effective_from' => '2026-01-01',
            ])->assertSuccessful();
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->putJson("/api/v1/inventory/price-lists/{$priceListId}/prices", [
                'product_variant_id' => $variantId,
                'price' => 30000,
                'effective_from' => '2026-06-01',
            ])->assertSuccessful();

        // Newest effective_from first; dates serialized as plain YYYY-MM-DD.
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/price-lists/{$priceListId}/prices")
            ->assertSuccessful()
            ->assertJsonCount(2, 'data.prices')
            ->assertJsonPath('data.prices.0.price_list_id', $priceListId)
            ->assertJsonPath('data.prices.0.product_variant_id', $variantId)
            ->assertJsonPath('data.prices.0.price', '30000.0000')
            ->assertJsonPath('data.prices.0.effective_from', '2026-06-01')
            ->assertJsonPath('data.prices.1.price', '25000.0000')
            ->assertJsonPath('data.prices.1.effective_from', '2026-01-01');
    });

    it('filters price rows by product variant', function (): void {
        [, $token, $companyId] = inventoryActor();
        $uom = createUnit($token, $companyId, 'pcs');
        $productId = createProduct($token, $companyId, [
            'name' => 'Milk',
            'base_uom_id' => $uom,
            'variants' => [['sku' => 'PRC-4'], ['sku' => 'PRC-5']],
        ]);
        $variants = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/products/{$productId}")
            ->json('data.product.variants');
        $variantA = $variants[0]['id'];
        $variantB = $variants[1]['id'];

        $priceListId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/inventory/price-lists', ['name' => 'Retail'])
            ->assertCreated()
            ->json('data.price_list.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->putJson("/api/v1/inventory/price-lists/{$priceListId}/prices", [
                'product_variant_id' => $variantA,
                'price' => 10000,
                'effective_from' => '2026-01-01',
            ])->assertSuccessful();
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->putJson("/api/v1/inventory/price-lists/{$priceListId}/prices", [
                'product_variant_id' => $variantB,
                'price' => 20000,
                'effective_from' => '2026-01-01',
            ])->assertSuccessful();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/price-lists/{$priceListId}/prices?product_variant_id={$variantB}")
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.prices')
            ->assertJsonPath('data.prices.0.product_variant_id', $variantB)
            ->assertJsonPath('data.prices.0.price', '20000.0000');
    });

    it('returns 404 when reading prices of another company\'s price list', function (): void {
        [, $tokenA, $companyA] = inventoryActor();
        [, $tokenB, $companyB] = inventoryActor();

        $priceListId = $this->withToken($tokenA)->withHeader('X-Company-Id', (string) $companyA)
            ->postJson('/api/v1/inventory/price-lists', ['name' => 'Retail'])
            ->assertCreated()
            ->json('data.price_list.id');

        $this->withToken($tokenB)->withHeader('X-Company-Id', (string) $companyB)
            ->getJson("/api/v1/inventory/price-lists/{$priceListId}/prices")
            ->assertNotFound();
    });

    it('lists price lists for the company', function (): void {
        [, $token, $companyId] = inventoryActor();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/inventory/price-lists', ['name' => 'Wholesale'])
            ->assertCreated();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/inventory/price-lists')
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.price_lists');
    });
});
