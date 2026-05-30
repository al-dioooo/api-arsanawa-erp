<?php

use App\Models\User;
use App\Modules\Organization\Models\Membership;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
});

describe('Inventory product units', function () {
    it('creates, lists, shows, updates and deletes a sellable product unit', function (): void {
        [, $token, $companyId] = inventoryActor();
        $uomId = createUnit($token, $companyId, 'BOX', 'Box');
        $productId = createProduct($token, $companyId, [
            'name' => 'Nasi Box Premium',
            'base_uom_id' => $uomId,
        ]);
        $sizeGroup = createVariantGroup($token, $companyId, 'Package Size', 'package-size', $uomId);
        $proteinGroup = createVariantGroup($token, $companyId, 'Protein', 'protein', $uomId);
        $sizeId = createVariant($token, $companyId, $sizeGroup, '25 Pax', '25-pax');
        $proteinId = createVariant($token, $companyId, $proteinGroup, 'Ayam', 'ayam');

        $unitId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/inventory/product-units', [
                'product_id' => $productId,
                'sku' => 'SKL-NBP-25-AYM',
                'barcode' => '899700000001',
                'name' => 'Nasi Box Premium 25 Pax Ayam',
                'variant_ids' => [$sizeId, $proteinId],
            ])
            ->assertCreated()
            ->assertJsonPath('data.product_unit.sku', 'SKL-NBP-25-AYM')
            ->assertJsonCount(2, 'data.product_unit.variants')
            ->json('data.product_unit.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/product-units?product_id={$productId}")
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.product_units')
            ->assertJsonPath('data.product_units.0.product.id', $productId);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/product-units/{$unitId}")
            ->assertSuccessful()
            ->assertJsonPath('data.product_unit.name', 'Nasi Box Premium 25 Pax Ayam');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->patchJson("/api/v1/inventory/product-units/{$unitId}", [
                'sku' => 'SKL-NBP-25-AYM-A',
                'is_active' => false,
                'variant_ids' => [$proteinId],
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.product_unit.sku', 'SKL-NBP-25-AYM-A')
            ->assertJsonPath('data.product_unit.is_active', false)
            ->assertJsonCount(1, 'data.product_unit.variants');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->deleteJson("/api/v1/inventory/product-units/{$unitId}")
            ->assertSuccessful();

        $this->assertDatabaseMissing('product_units', ['id' => $unitId]);
    });

    it('rejects duplicate SKUs within a company', function (): void {
        [, $token, $companyId] = inventoryActor();
        $uomId = createUnit($token, $companyId, 'PCS');
        $productId = createProduct($token, $companyId, [
            'name' => 'Snack Box',
            'base_uom_id' => $uomId,
        ]);

        $payload = [
            'product_id' => $productId,
            'sku' => 'SKL-SNB-001',
            'name' => 'Snack Box Regular',
            'variant_ids' => [],
        ];

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/inventory/product-units', $payload)
            ->assertCreated();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/inventory/product-units', $payload)
            ->assertUnprocessable();
    });

    it('rejects variants belonging to another company', function (): void {
        [, $tokenA, $companyA] = inventoryActor();
        [, $tokenB, $companyB] = inventoryActor();
        $uomA = createUnit($tokenA, $companyA, 'PCS');
        $uomB = createUnit($tokenB, $companyB, 'PCS');
        $productB = createProduct($tokenB, $companyB, [
            'name' => 'B Product',
            'base_uom_id' => $uomB,
        ]);
        $foreignGroup = createVariantGroup($tokenA, $companyA, 'Foreign', 'foreign', $uomA);
        $foreignVariant = createVariant($tokenA, $companyA, $foreignGroup, 'Foreign Value', 'foreign-value');

        $this->withToken($tokenB)->withHeader('X-Company-Id', (string) $companyB)
            ->postJson('/api/v1/inventory/product-units', [
                'product_id' => $productB,
                'sku' => 'BAD-UNIT',
                'variant_ids' => [$foreignVariant],
            ])
            ->assertUnprocessable();
    });

    it('denies users without inventory.manage-products', function (): void {
        [, $token, $companyId] = inventoryActor();
        $uomId = createUnit($token, $companyId, 'PCS');
        $productId = createProduct($token, $companyId, [
            'name' => 'Nope Product',
            'base_uom_id' => $uomId,
        ]);

        $member = User::factory()->create();
        Membership::create([
            'company_id' => $companyId,
            'user_id' => $member->id,
            'role' => 'member',
            'status' => 'active',
        ]);
        $memberToken = $this->postJson('/api/v1/auth/login', [
            'login' => $member->email,
            'password' => 'password',
        ])->json('data.access_token');

        $this->withToken($memberToken)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/inventory/product-units', [
                'product_id' => $productId,
                'sku' => 'NOPE',
                'variant_ids' => [],
            ])
            ->assertForbidden();
    });
});
