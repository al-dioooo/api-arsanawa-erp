<?php

use App\Models\User;
use App\Modules\Organization\Models\Membership;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
});

describe('Inventory products', function () {
    it('creates a product with inline variants', function (): void {
        [, $token, $companyId] = inventoryActor();
        $uom = createUnit($token, $companyId, 'pcs');
        $category = createCategory($token, $companyId, 'Food');

        $response = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/inventory/products', [
                'name' => 'Nasi Goreng',
                'base_uom_id' => $uom,
                'category_id' => $category,
                'variants' => [
                    ['sku' => 'NSG-S', 'name' => 'Small'],
                    ['sku' => 'NSG-L', 'name' => 'Large'],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.product.name', 'Nasi Goreng')
            ->assertJsonCount(2, 'data.product.variants');

        $productId = $response->json('data.product.id');

        $this->assertDatabaseHas('product_variants', [
            'sku' => 'NSG-S',
            'company_id' => $companyId,
            'product_id' => $productId,
        ]);
        $this->assertDatabaseHas('product_variants', ['sku' => 'NSG-L', 'company_id' => $companyId]);
    });

    it('lists products with filter and search', function (): void {
        [, $token, $companyId] = inventoryActor();
        $uom = createUnit($token, $companyId, 'pcs');
        $category = createCategory($token, $companyId, 'Food');

        createProduct($token, $companyId, [
            'name' => 'Nasi Goreng Spesial',
            'base_uom_id' => $uom,
            'category_id' => $category,
            'variants' => [['sku' => 'A1']],
        ]);
        createProduct($token, $companyId, [
            'name' => 'Es Teh',
            'base_uom_id' => $uom,
            'variants' => [['sku' => 'B1']],
        ]);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/inventory/products?search=Nasi')
            ->assertSuccessful()
            ->assertJsonStructure(['data' => ['products', 'pagination']])
            ->assertJsonCount(1, 'data.products');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/products?category_id={$category}")
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.products');
    });

    it('shows, updates and deletes a product (cascading variants)', function (): void {
        [, $token, $companyId] = inventoryActor();
        $uom = createUnit($token, $companyId, 'pcs');
        $productId = createProduct($token, $companyId, [
            'name' => 'Soto',
            'base_uom_id' => $uom,
            'variants' => [['sku' => 'SOTO-1']],
        ]);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/products/{$productId}")
            ->assertSuccessful()
            ->assertJsonPath('data.product.name', 'Soto');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->patchJson("/api/v1/inventory/products/{$productId}", ['name' => 'Soto Ayam'])
            ->assertSuccessful()
            ->assertJsonPath('data.product.name', 'Soto Ayam');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->deleteJson("/api/v1/inventory/products/{$productId}")
            ->assertSuccessful();

        $this->assertDatabaseMissing('products', ['id' => $productId]);
        $this->assertDatabaseMissing('product_variants', ['product_id' => $productId]);
    });

    it('returns 404 for a product belonging to another company', function (): void {
        [, $tokenA, $companyA] = inventoryActor();
        [, $tokenB, $companyB] = inventoryActor();
        $uom = createUnit($tokenA, $companyA, 'pcs');
        $productId = createProduct($tokenA, $companyA, [
            'name' => 'A-only',
            'base_uom_id' => $uom,
            'variants' => [['sku' => 'X1']],
        ]);

        $this->withToken($tokenB)->withHeader('X-Company-Id', (string) $companyB)
            ->getJson("/api/v1/inventory/products/{$productId}")
            ->assertNotFound();
    });

    it('rejects a base unit of measure from another company', function (): void {
        [, $tokenA, $companyA] = inventoryActor();
        [, $tokenB, $companyB] = inventoryActor();
        $foreignUom = createUnit($tokenA, $companyA, 'pcs');

        $this->withToken($tokenB)->withHeader('X-Company-Id', (string) $companyB)
            ->postJson('/api/v1/inventory/products', [
                'name' => 'Bad Product',
                'base_uom_id' => $foreignUom,
                'variants' => [['sku' => 'BAD-1']],
            ])
            ->assertUnprocessable();
    });

    it('denies users without inventory.manage-products', function (): void {
        [, $token, $companyId] = inventoryActor();
        $uom = createUnit($token, $companyId, 'pcs');

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
            ->postJson('/api/v1/inventory/products', [
                'name' => 'Nope',
                'base_uom_id' => $uom,
                'variants' => [['sku' => 'NOPE-1']],
            ])
            ->assertForbidden();
    });
});
