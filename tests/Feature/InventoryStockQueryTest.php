<?php

use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
});

describe('Inventory stock queries', function () {
    it('filters stock levels lots movements valuation and detail by product unit', function (): void {
        [, $token, $companyId, $branchId] = inventoryActor();
        $uom = createUnit($token, $companyId, 'pcs');
        $productId = createProduct($token, $companyId, [
            'name' => 'Catering Package',
            'base_uom_id' => $uom,
        ]);
        $firstUnitId = createProductUnit($token, $companyId, $productId, 'SKL-CT-25');
        $secondUnitId = createProductUnit($token, $companyId, $productId, 'SKL-CT-50');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/stock/receipts', [
                'product_unit_id' => $firstUnitId,
                'branch_id' => $branchId,
                'quantity' => 10,
                'unit_cost' => 10000,
                'lot_number' => 'PU-LOT-1',
            ])->assertCreated();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/stock/receipts', [
                'product_unit_id' => $secondUnitId,
                'branch_id' => $branchId,
                'quantity' => 5,
                'unit_cost' => 50000,
                'lot_number' => 'PU-LOT-2',
            ])->assertCreated();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/stock/levels?product_unit_id={$firstUnitId}&branch_id={$branchId}")
            ->assertSuccessful()
            ->assertJsonPath('data.on_hand', '10.0000');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/stock/lots?product_unit_id={$firstUnitId}&branch_id={$branchId}")
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.lots')
            ->assertJsonPath('data.lots.0.product_unit_id', $firstUnitId);

        $movementId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/stock/movements?product_unit_id={$firstUnitId}&branch_id={$branchId}")
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.movements')
            ->assertJsonPath('data.movements.0.product_unit_id', $firstUnitId)
            ->json('data.movements.0.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/stock/valuation?product_unit_id={$firstUnitId}&branch_id={$branchId}")
            ->assertSuccessful()
            ->assertJsonPath('data.total_value', '100000.0000');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/stock/movements/{$movementId}")
            ->assertSuccessful()
            ->assertJsonPath('data.movement.id', $movementId)
            ->assertJsonPath('data.movement.product_unit_id', $firstUnitId)
            ->assertJsonPath('data.movement.product_unit.id', $firstUnitId);
    });

    it('does not expose stock movement detail across companies', function (): void {
        [, $tokenA, $companyA, $branchA] = inventoryActor();
        [, $tokenB, $companyB] = inventoryActor();
        $uom = createUnit($tokenA, $companyA, 'pcs');
        $productId = createProduct($tokenA, $companyA, [
            'name' => 'Private Stock',
            'base_uom_id' => $uom,
        ]);
        $productUnitId = createProductUnit($tokenA, $companyA, $productId, 'PRIVATE-SKU');

        $this->withToken($tokenA)->withHeader('X-Company-Id', (string) $companyA)
            ->withHeader('X-Branch-Id', (string) $branchA)
            ->postJson('/api/v1/inventory/stock/receipts', [
                'product_unit_id' => $productUnitId,
                'branch_id' => $branchA,
                'quantity' => 1,
                'unit_cost' => 1000,
            ])->assertCreated();

        $movementId = $this->withToken($tokenA)->withHeader('X-Company-Id', (string) $companyA)
            ->getJson("/api/v1/inventory/stock/movements?product_unit_id={$productUnitId}&branch_id={$branchA}")
            ->json('data.movements.0.id');

        $this->withToken($tokenB)->withHeader('X-Company-Id', (string) $companyB)
            ->getJson("/api/v1/inventory/stock/movements/{$movementId}")
            ->assertForbidden();
    });

    it('returns on-hand per variant and branch', function (): void {
        [, $token, $companyId, $branchId] = inventoryActor();
        $uom = createUnit($token, $companyId, 'pcs');
        $productId = createProduct($token, $companyId, [
            'name' => 'Rice',
            'base_uom_id' => $uom,
            'variants' => [['sku' => 'QRY-1']],
        ]);
        $variantId = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/products/{$productId}")
            ->json('data.product.variants.0.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/stock/receipts', [
                'product_variant_id' => $variantId,
                'branch_id' => $branchId,
                'quantity' => 25,
                'unit_cost' => 5000,
            ])->assertCreated();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/stock/levels?product_variant_id={$variantId}&branch_id={$branchId}")
            ->assertSuccessful()
            ->assertJsonPath('data.on_hand', '25.0000');
    });

    it('lists stock lots with expiry filter', function (): void {
        [, $token, $companyId, $branchId] = inventoryActor();
        $uom = createUnit($token, $companyId, 'pcs');
        $productId = createProduct($token, $companyId, [
            'name' => 'Milk',
            'base_uom_id' => $uom,
            'variants' => [['sku' => 'QRY-2']],
        ]);
        $variantId = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/products/{$productId}")
            ->json('data.product.variants.0.id');

        // Lot that expires soon
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/stock/receipts', [
                'product_variant_id' => $variantId,
                'branch_id' => $branchId,
                'quantity' => 10,
                'unit_cost' => 8000,
                'expiry_date' => '2026-06-01',
                'received_at' => '2026-01-01',
            ])->assertCreated();

        // Lot that expires later
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/stock/receipts', [
                'product_variant_id' => $variantId,
                'branch_id' => $branchId,
                'quantity' => 10,
                'unit_cost' => 8000,
                'expiry_date' => '2027-01-01',
                'received_at' => '2026-02-01',
            ])->assertCreated();

        // Filter lots expiring before 2026-07-01
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/stock/lots?branch_id={$branchId}&expiring_before=2026-07-01")
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.lots');

        // The list is paginated and honours per_page with meta for the client.
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/stock/lots?branch_id={$branchId}&per_page=1")
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.lots')
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.pagination.last_page', 2);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/stock/lots?branch_id={$branchId}&per_page=1&page=2")
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.lots')
            ->assertJsonPath('data.pagination.current_page', 2);
    });

    it('lists stock movements (paginated ledger)', function (): void {
        [, $token, $companyId, $branchId] = inventoryActor();
        $uom = createUnit($token, $companyId, 'pcs');
        $productId = createProduct($token, $companyId, [
            'name' => 'Salt',
            'base_uom_id' => $uom,
            'variants' => [['sku' => 'QRY-3']],
        ]);
        $variantId = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/products/{$productId}")
            ->json('data.product.variants.0.id');

        // Receipt
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/stock/receipts', [
                'product_variant_id' => $variantId,
                'branch_id' => $branchId,
                'quantity' => 50,
                'unit_cost' => 2000,
            ])->assertCreated();

        // Issue
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/stock/issues', [
                'product_variant_id' => $variantId,
                'branch_id' => $branchId,
                'quantity' => 10,
            ])->assertSuccessful();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/stock/movements?branch_id={$branchId}")
            ->assertSuccessful()
            ->assertJsonStructure(['data' => ['movements', 'pagination']]);
    });

    it('filters stock movements by type', function (): void {
        [, $token, $companyId, $branchId] = inventoryActor();
        $uom = createUnit($token, $companyId, 'pcs');
        $productId = createProduct($token, $companyId, [
            'name' => 'Flour',
            'base_uom_id' => $uom,
            'variants' => [['sku' => 'QRY-TYPE']],
        ]);
        $variantId = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/products/{$productId}")
            ->json('data.product.variants.0.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/stock/receipts', [
                'product_variant_id' => $variantId,
                'branch_id' => $branchId,
                'quantity' => 50,
                'unit_cost' => 2000,
            ])->assertCreated();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/stock/issues', [
                'product_variant_id' => $variantId,
                'branch_id' => $branchId,
                'quantity' => 10,
            ])->assertSuccessful();

        // No filter: both the receipt and the issue movement are returned.
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/stock/movements?branch_id={$branchId}")
            ->assertSuccessful()
            ->assertJsonCount(2, 'data.movements');

        // type=receipt narrows the ledger to receipt movements only.
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/stock/movements?branch_id={$branchId}&type=receipt")
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.movements')
            ->assertJsonPath('data.movements.0.type', 'receipt');

        // Unknown movement types are rejected rather than silently ignored.
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/stock/movements?branch_id={$branchId}&type=bogus")
            ->assertStatus(422);
    });

    it('returns stock valuation', function (): void {
        [, $token, $companyId, $branchId] = inventoryActor();
        $uom = createUnit($token, $companyId, 'pcs');
        $productId = createProduct($token, $companyId, [
            'name' => 'Pepper',
            'base_uom_id' => $uom,
            'variants' => [['sku' => 'QRY-4']],
        ]);
        $variantId = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/products/{$productId}")
            ->json('data.product.variants.0.id');

        // 10 units @ 5000 = 50000
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/stock/receipts', [
                'product_variant_id' => $variantId,
                'branch_id' => $branchId,
                'quantity' => 10,
                'unit_cost' => 5000,
            ])->assertCreated();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/inventory/stock/valuation')
            ->assertSuccessful()
            ->assertJsonPath('data.total_value', '50000.0000');
    });
});
