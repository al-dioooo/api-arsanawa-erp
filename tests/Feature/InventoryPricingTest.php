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
