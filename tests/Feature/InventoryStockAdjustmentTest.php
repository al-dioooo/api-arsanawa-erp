<?php

use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
});

describe('Inventory stock issues and adjustments', function () {
    it('issues stock FIFO across multiple lots', function (): void {
        [, $token, $companyId, $branchId] = inventoryActor();
        $uom = createUnit($token, $companyId, 'pcs');
        $productId = createProduct($token, $companyId, [
            'name' => 'Coffee',
            'base_uom_id' => $uom,
            'variants' => [['sku' => 'ISS-1']],
        ]);
        $variantId = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/products/{$productId}")
            ->json('data.product.variants.0.id');

        // Receipt 1 — older, cost 1000
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/stock/receipts', [
                'product_variant_id' => $variantId,
                'branch_id' => $branchId,
                'quantity' => 10,
                'unit_cost' => 1000,
                'received_at' => '2026-01-01',
            ])->assertCreated();

        // Receipt 2 — newer, cost 1200
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/stock/receipts', [
                'product_variant_id' => $variantId,
                'branch_id' => $branchId,
                'quantity' => 10,
                'unit_cost' => 1200,
                'received_at' => '2026-02-01',
            ])->assertCreated();

        // Issue 15 — should consume all of lot 1 (10 @ 1000) + 5 of lot 2 (@ 1200)
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/stock/issues', [
                'product_variant_id' => $variantId,
                'branch_id' => $branchId,
                'quantity' => 15,
            ])->assertSuccessful();

        // Check on-hand
        $response = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/stock/levels?product_variant_id={$variantId}&branch_id={$branchId}");

        $response->assertSuccessful()
            ->assertJsonPath('data.on_hand', '5.0000');

        // Lot 1 should be depleted
        $this->assertDatabaseHas('stock_lots', [
            'product_variant_id' => $variantId,
            'branch_id' => $branchId,
            'unit_cost' => '1000.0000',
            'status' => 'depleted',
        ]);
    });

    it('rejects over-issue', function (): void {
        [, $token, $companyId, $branchId] = inventoryActor();
        $uom = createUnit($token, $companyId, 'pcs');
        $productId = createProduct($token, $companyId, [
            'name' => 'Sugar',
            'base_uom_id' => $uom,
            'variants' => [['sku' => 'OI-1']],
        ]);
        $variantId = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/products/{$productId}")
            ->json('data.product.variants.0.id');

        // Receipt 5 units
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/stock/receipts', [
                'product_variant_id' => $variantId,
                'branch_id' => $branchId,
                'quantity' => 5,
                'unit_cost' => 2000,
            ])->assertCreated();

        // Issue 6 — should be rejected
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/stock/issues', [
                'product_variant_id' => $variantId,
                'branch_id' => $branchId,
                'quantity' => 6,
            ])->assertUnprocessable();

        // On-hand unchanged
        $response = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/stock/levels?product_variant_id={$variantId}&branch_id={$branchId}");

        $response->assertJsonPath('data.on_hand', '5.0000');
    });

    it('adjusts stock upward (creates a lot)', function (): void {
        [, $token, $companyId, $branchId] = inventoryActor();
        $uom = createUnit($token, $companyId, 'pcs');
        $productId = createProduct($token, $companyId, [
            'name' => 'Flour',
            'base_uom_id' => $uom,
            'variants' => [['sku' => 'ADJ-UP']],
        ]);
        $variantId = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/products/{$productId}")
            ->json('data.product.variants.0.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/stock/adjustments', [
                'product_variant_id' => $variantId,
                'branch_id' => $branchId,
                'quantity' => 20,
                'unit_cost' => 3000,
            ])->assertSuccessful();

        $response = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/stock/levels?product_variant_id={$variantId}&branch_id={$branchId}");

        $response->assertJsonPath('data.on_hand', '20.0000');
    });

    it('adjusts stock downward (consumes FIFO)', function (): void {
        [, $token, $companyId, $branchId] = inventoryActor();
        $uom = createUnit($token, $companyId, 'pcs');
        $productId = createProduct($token, $companyId, [
            'name' => 'Butter',
            'base_uom_id' => $uom,
            'variants' => [['sku' => 'ADJ-DN']],
        ]);
        $variantId = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/products/{$productId}")
            ->json('data.product.variants.0.id');

        // Receipt first
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/stock/receipts', [
                'product_variant_id' => $variantId,
                'branch_id' => $branchId,
                'quantity' => 30,
                'unit_cost' => 4000,
            ])->assertCreated();

        // Adjust down by 10
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/stock/adjustments', [
                'product_variant_id' => $variantId,
                'branch_id' => $branchId,
                'quantity' => -10,
            ])->assertSuccessful();

        $response = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/stock/levels?product_variant_id={$variantId}&branch_id={$branchId}");

        $response->assertJsonPath('data.on_hand', '20.0000');
    });
});
