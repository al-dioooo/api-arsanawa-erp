<?php

use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
});

describe('Inventory stock receipts', function () {
    it('records a receipt with a product unit sellable SKU', function (): void {
        [, $token, $companyId, $branchId] = inventoryActor();
        $uom = createUnit($token, $companyId, 'pcs');
        $productId = createProduct($token, $companyId, [
            'name' => 'Nasi Box Regular',
            'base_uom_id' => $uom,
        ]);
        $productUnitId = createProductUnit($token, $companyId, $productId, 'SKL-NBR-25');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/stock/receipts', [
                'product_unit_id' => $productUnitId,
                'branch_id' => $branchId,
                'quantity' => 25,
                'unit_cost' => 35000,
                'received_at' => '2026-01-15',
            ])
            ->assertCreated()
            ->assertJsonPath('data.lot.product_unit_id', $productUnitId)
            ->assertJsonPath('data.lot.product_unit.id', $productUnitId)
            ->assertJsonPath('data.lot.remaining_quantity', '25.0000');

        $this->assertDatabaseHas('stock_lots', [
            'product_unit_id' => $productUnitId,
            'branch_id' => $branchId,
            'remaining_quantity' => '25.0000',
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_unit_id' => $productUnitId,
            'branch_id' => $branchId,
            'type' => 'receipt',
            'quantity' => '25.0000',
        ]);
    });

    it('records a receipt with a lot and movement', function (): void {
        [, $token, $companyId, $branchId] = inventoryActor();
        $uom = createUnit($token, $companyId, 'pcs');
        $productId = createProduct($token, $companyId, [
            'name' => 'Coffee Beans',
            'base_uom_id' => $uom,
            'variants' => [['sku' => 'CB-001']],
        ]);
        $variantId = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/products/{$productId}")
            ->json('data.product.variants.0.id');

        $response = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/stock/receipts', [
                'product_variant_id' => $variantId,
                'branch_id' => $branchId,
                'quantity' => 100,
                'unit_cost' => 5000,
                'received_at' => '2026-01-15',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.lot.remaining_quantity', '100.0000')
            ->assertJsonPath('data.lot.unit_cost', '5000.0000');

        $this->assertDatabaseHas('stock_lots', [
            'product_variant_id' => $variantId,
            'branch_id' => $branchId,
            'remaining_quantity' => '100.0000',
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_variant_id' => $variantId,
            'branch_id' => $branchId,
            'type' => 'receipt',
            'quantity' => '100.0000',
        ]);
    });

    it('rejects receipt when branch does not belong to company', function (): void {
        [, $tokenA, $companyA] = inventoryActor();
        [, , , $branchB] = inventoryActor();
        $uom = createUnit($tokenA, $companyA, 'pcs');
        $productId = createProduct($tokenA, $companyA, [
            'name' => 'Coffee',
            'base_uom_id' => $uom,
            'variants' => [['sku' => 'RCV-F1']],
        ]);
        $variantId = test()->withToken($tokenA)->withHeader('X-Company-Id', (string) $companyA)
            ->getJson("/api/v1/inventory/products/{$productId}")
            ->json('data.product.variants.0.id');

        $this->withToken($tokenA)->withHeader('X-Company-Id', (string) $companyA)
            ->postJson('/api/v1/inventory/stock/receipts', [
                'product_variant_id' => $variantId,
                'branch_id' => $branchB,
                'quantity' => 10,
                'unit_cost' => 1000,
            ])
            ->assertUnprocessable();
    });

    it('records receipt with optional lot number and expiry', function (): void {
        [, $token, $companyId, $branchId] = inventoryActor();
        $uom = createUnit($token, $companyId, 'pcs');
        $productId = createProduct($token, $companyId, [
            'name' => 'Milk',
            'base_uom_id' => $uom,
            'variants' => [['sku' => 'MLK-001']],
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
                'unit_cost' => 8000,
                'lot_number' => 'LOT-2026-A',
                'expiry_date' => '2026-06-30',
                'received_at' => '2026-01-01',
            ])
            ->assertCreated()
            ->assertJsonPath('data.lot.lot_number', 'LOT-2026-A');

        $this->assertDatabaseHas('stock_lots', [
            'product_variant_id' => $variantId,
            'lot_number' => 'LOT-2026-A',
        ]);
    });
});
