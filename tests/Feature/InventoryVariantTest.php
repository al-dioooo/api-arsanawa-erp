<?php

use App\Modules\Inventory\Models\ProductBranchAvailability;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
});

describe('Inventory product variants', function () {
    it('adds, updates and deletes a variant', function (): void {
        [, $token, $companyId] = inventoryActor();
        $uom = createUnit($token, $companyId, 'pcs');
        $productId = createProduct($token, $companyId, [
            'name' => 'Coffee',
            'base_uom_id' => $uom,
            'variants' => [['sku' => 'COF-1']],
        ]);

        $variantId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/inventory/products/{$productId}/variants", ['sku' => 'COF-2', 'name' => 'Large'])
            ->assertCreated()
            ->assertJsonPath('data.variant.sku', 'COF-2')
            ->json('data.variant.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->patchJson("/api/v1/inventory/products/{$productId}/variants/{$variantId}", ['name' => 'Extra Large'])
            ->assertSuccessful()
            ->assertJsonPath('data.variant.name', 'Extra Large');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->deleteJson("/api/v1/inventory/products/{$productId}/variants/{$variantId}")
            ->assertSuccessful();

        $this->assertDatabaseMissing('product_variants', ['id' => $variantId]);
    });

    it('rejects a duplicate SKU', function (): void {
        [, $token, $companyId] = inventoryActor();
        $uom = createUnit($token, $companyId, 'pcs');
        $productId = createProduct($token, $companyId, [
            'name' => 'Coffee',
            'base_uom_id' => $uom,
            'variants' => [['sku' => 'DUP-1']],
        ]);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/inventory/products/{$productId}/variants", ['sku' => 'DUP-1'])
            ->assertUnprocessable();
    });

    it('syncs product tags', function (): void {
        [, $token, $companyId] = inventoryActor();
        $uom = createUnit($token, $companyId, 'pcs');
        $productId = createProduct($token, $companyId, [
            'name' => 'Coffee',
            'base_uom_id' => $uom,
            'variants' => [['sku' => 'TAG-1']],
        ]);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->putJson("/api/v1/inventory/products/{$productId}/tags", ['tags' => ['hot', 'bestseller']])
            ->assertSuccessful()
            ->assertJsonCount(2, 'data.product.tags');

        $this->assertDatabaseHas('tags', ['company_id' => $companyId, 'name' => 'hot']);
    });

    it('sets per-branch availability', function (): void {
        [, $token, $companyId, $branchId] = inventoryActor();
        $uom = createUnit($token, $companyId, 'pcs');
        $productId = createProduct($token, $companyId, [
            'name' => 'Coffee',
            'base_uom_id' => $uom,
            'variants' => [['sku' => 'AV-1']],
        ]);
        $variantId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/products/{$productId}")
            ->json('data.product.variants.0.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->putJson("/api/v1/inventory/products/{$productId}/variants/{$variantId}/availability", [
                'branch_id' => $branchId,
                'is_available' => true,
                'is_exclusive' => true,
            ])
            ->assertSuccessful();

        $this->assertDatabaseHas('product_branch_availability', [
            'branch_id' => $branchId,
            'product_variant_id' => $variantId,
            'is_exclusive' => true,
        ]);

        // Re-setting updates rather than duplicating.
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->putJson("/api/v1/inventory/products/{$productId}/variants/{$variantId}/availability", [
                'branch_id' => $branchId,
                'is_available' => false,
            ])
            ->assertSuccessful();

        expect(ProductBranchAvailability::query()
            ->where('branch_id', $branchId)
            ->where('product_variant_id', $variantId)
            ->count())->toBe(1);
    });

    it('rejects branch availability for a branch in another company', function (): void {
        [, $tokenA, $companyA] = inventoryActor();
        [, , , $branchB] = inventoryActor();
        $uom = createUnit($tokenA, $companyA, 'pcs');
        $productId = createProduct($tokenA, $companyA, [
            'name' => 'Coffee',
            'base_uom_id' => $uom,
            'variants' => [['sku' => 'FRG-1']],
        ]);
        $variantId = $this->withToken($tokenA)->withHeader('X-Company-Id', (string) $companyA)
            ->getJson("/api/v1/inventory/products/{$productId}")
            ->json('data.product.variants.0.id');

        $this->withToken($tokenA)->withHeader('X-Company-Id', (string) $companyA)
            ->putJson("/api/v1/inventory/products/{$productId}/variants/{$variantId}/availability", [
                'branch_id' => $branchB,
                'is_available' => true,
            ])
            ->assertUnprocessable();
    });
});
