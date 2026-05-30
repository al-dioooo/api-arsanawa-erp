<?php

use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
});

describe('Inventory stock transfers', function () {
    it('transfers stock between branches by product unit sellable SKU', function (): void {
        [, $token, $companyId, $branchA] = inventoryActor();

        $branchB = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/organization/companies/{$companyId}/branches", [
                'name' => 'Branch Product Unit',
            ])->json('data.branch.id');

        $uom = createUnit($token, $companyId, 'pcs');
        $productId = createProduct($token, $companyId, [
            'name' => 'Premium Nasi Box',
            'base_uom_id' => $uom,
        ]);
        $productUnitId = createProductUnit($token, $companyId, $productId, 'SKL-NBP-25');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchA)
            ->postJson('/api/v1/inventory/stock/receipts', [
                'product_unit_id' => $productUnitId,
                'branch_id' => $branchA,
                'quantity' => 10,
                'unit_cost' => 55000,
            ])->assertCreated();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchA)
            ->postJson('/api/v1/inventory/stock/transfers', [
                'from_branch_id' => $branchA,
                'to_branch_id' => $branchB,
                'items' => [
                    ['product_unit_id' => $productUnitId, 'quantity' => 4],
                ],
            ])->assertCreated()
            ->assertJsonPath('data.transfer.items.0.product_unit_id', $productUnitId);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/stock/levels?product_unit_id={$productUnitId}&branch_id={$branchA}")
            ->assertJsonPath('data.on_hand', '6.0000');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/stock/levels?product_unit_id={$productUnitId}&branch_id={$branchB}")
            ->assertJsonPath('data.on_hand', '4.0000');

        $this->assertDatabaseHas('stock_movements', [
            'product_unit_id' => $productUnitId,
            'branch_id' => $branchA,
            'type' => 'transfer_out',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'product_unit_id' => $productUnitId,
            'branch_id' => $branchB,
            'type' => 'transfer_in',
        ]);
    });

    it('transfers stock between branches preserving cost', function (): void {
        [, $token, $companyId, $branchA] = inventoryActor();

        // Create a second branch
        $branchB = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/organization/companies/{$companyId}/branches", [
                'name' => 'Branch B',
            ])->json('data.branch.id');

        $uom = createUnit($token, $companyId, 'pcs');
        $productId = createProduct($token, $companyId, [
            'name' => 'Coffee',
            'base_uom_id' => $uom,
            'variants' => [['sku' => 'TRF-1']],
        ]);
        $variantId = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/products/{$productId}")
            ->json('data.product.variants.0.id');

        // Receipt at branch A: 10 units @ 1000
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchA)
            ->postJson('/api/v1/inventory/stock/receipts', [
                'product_variant_id' => $variantId,
                'branch_id' => $branchA,
                'quantity' => 10,
                'unit_cost' => 1000,
            ])->assertCreated();

        // Transfer 4 from A → B
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchA)
            ->postJson('/api/v1/inventory/stock/transfers', [
                'from_branch_id' => $branchA,
                'to_branch_id' => $branchB,
                'items' => [
                    ['product_variant_id' => $variantId, 'quantity' => 4],
                ],
            ])->assertCreated();

        // Branch A on-hand should be 6
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/stock/levels?product_variant_id={$variantId}&branch_id={$branchA}")
            ->assertJsonPath('data.on_hand', '6.0000');

        // Branch B on-hand should be 4
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/stock/levels?product_variant_id={$variantId}&branch_id={$branchB}")
            ->assertJsonPath('data.on_hand', '4.0000');

        // Cost preserved at destination
        $this->assertDatabaseHas('stock_lots', [
            'product_variant_id' => $variantId,
            'branch_id' => $branchB,
            'unit_cost' => '1000.0000',
        ]);

        // Paired movements exist
        $this->assertDatabaseHas('stock_movements', [
            'product_variant_id' => $variantId,
            'branch_id' => $branchA,
            'type' => 'transfer_out',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'product_variant_id' => $variantId,
            'branch_id' => $branchB,
            'type' => 'transfer_in',
        ]);
    });

    it('rejects transfer exceeding source on-hand', function (): void {
        [, $token, $companyId, $branchA] = inventoryActor();

        $branchB = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/organization/companies/{$companyId}/branches", [
                'name' => 'Branch C',
            ])->json('data.branch.id');

        $uom = createUnit($token, $companyId, 'pcs');
        $productId = createProduct($token, $companyId, [
            'name' => 'Tea',
            'base_uom_id' => $uom,
            'variants' => [['sku' => 'TRF-FAIL']],
        ]);
        $variantId = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/products/{$productId}")
            ->json('data.product.variants.0.id');

        // Receipt 5
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchA)
            ->postJson('/api/v1/inventory/stock/receipts', [
                'product_variant_id' => $variantId,
                'branch_id' => $branchA,
                'quantity' => 5,
                'unit_cost' => 1000,
            ])->assertCreated();

        // Try to transfer 10 — should fail
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchA)
            ->postJson('/api/v1/inventory/stock/transfers', [
                'from_branch_id' => $branchA,
                'to_branch_id' => $branchB,
                'items' => [
                    ['product_variant_id' => $variantId, 'quantity' => 10],
                ],
            ])->assertUnprocessable();
    });
});
