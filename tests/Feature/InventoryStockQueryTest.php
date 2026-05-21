<?php

use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
});

describe('Inventory stock queries', function () {
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
