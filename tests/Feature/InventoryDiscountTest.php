<?php

use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
});

describe('Inventory discounts', function () {
    it('creates a discount with targets, dependencies, and giveaways', function (): void {
        [, $token, $companyId] = inventoryActor();
        $uom = createUnit($token, $companyId, 'pcs');

        $productA = createProduct($token, $companyId, [
            'name' => 'Product A', 'base_uom_id' => $uom, 'variants' => [['sku' => 'DSC-A']],
        ]);
        $variantA = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/products/{$productA}")
            ->json('data.product.variants.0.id');

        $productB = createProduct($token, $companyId, [
            'name' => 'Product B', 'base_uom_id' => $uom, 'variants' => [['sku' => 'DSC-B']],
        ]);
        $variantB = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/products/{$productB}")
            ->json('data.product.variants.0.id');

        $productC = createProduct($token, $companyId, [
            'name' => 'Product C', 'base_uom_id' => $uom, 'variants' => [['sku' => 'DSC-C']],
        ]);
        $variantC = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/products/{$productC}")
            ->json('data.product.variants.0.id');

        $discountId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/inventory/discounts', [
                'name' => 'Buy B get C free',
                'calculation_type' => 'percentage',
                'value' => 10,
                'effective_from' => '2026-01-01',
                'targets' => [['target_type' => 'variant', 'target_id' => $variantA]],
                'dependencies' => [['product_variant_id' => $variantB, 'required_quantity' => 2]],
                'giveaways' => [['product_variant_id' => $variantC, 'giveaway_quantity' => 1]],
            ])
            ->assertCreated()
            ->json('data.discount.id');

        // Verify show returns all relations
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/discounts/{$discountId}")
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.discount.targets')
            ->assertJsonCount(1, 'data.discount.dependencies')
            ->assertJsonCount(1, 'data.discount.giveaways');
    });

    it('lists and deletes discounts', function (): void {
        [, $token, $companyId] = inventoryActor();

        $discountId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/inventory/discounts', [
                'name' => 'Simple discount',
                'calculation_type' => 'amount',
                'value' => 5000,
                'effective_from' => '2026-01-01',
            ])
            ->assertCreated()
            ->json('data.discount.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/inventory/discounts')
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.discounts');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->deleteJson("/api/v1/inventory/discounts/{$discountId}")
            ->assertSuccessful();

        $this->assertDatabaseMissing('discounts', ['id' => $discountId]);
    });
});
