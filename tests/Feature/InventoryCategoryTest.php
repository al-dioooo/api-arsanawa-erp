<?php

use App\Models\User;
use App\Modules\Inventory\Models\Category;
use App\Modules\Organization\Models\Membership;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
});

describe('Inventory categories', function () {
    it('creates a nested tree with computed path and depth', function (): void {
        [, $token, $companyId] = inventoryActor();

        $food = createCategory($token, $companyId, 'Food');
        $drinks = createCategory($token, $companyId, 'Drinks', $food);
        $juice = createCategory($token, $companyId, 'Juice', $drinks);

        expect(Category::find($food)->depth)->toBe(0);
        expect(Category::find($drinks)->depth)->toBe(1);

        $juiceModel = Category::find($juice);
        expect($juiceModel->depth)->toBe(2);
        expect($juiceModel->path)
            ->toContain("/{$food}/")
            ->toContain("/{$drinks}/")
            ->toContain("/{$juice}/");
    });

    it('lists categories in tree order', function (): void {
        [, $token, $companyId] = inventoryActor();

        $food = createCategory($token, $companyId, 'Food');
        createCategory($token, $companyId, 'Drinks', $food);

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/inventory/categories')
            ->assertSuccessful()
            ->assertJsonStructure(['data' => ['categories' => [['id', 'name', 'parent_id', 'path', 'depth']]]])
            ->assertJsonCount(2, 'data.categories');
    });

    it('moves a subtree and recomputes descendant paths and depths', function (): void {
        [, $token, $companyId] = inventoryActor();

        $food = createCategory($token, $companyId, 'Food');
        $drinks = createCategory($token, $companyId, 'Drinks', $food);
        $juice = createCategory($token, $companyId, 'Juice', $drinks);

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/inventory/categories/{$drinks}/move", ['parent_id' => null])
            ->assertSuccessful();

        expect(Category::find($drinks)->depth)->toBe(0);
        expect(Category::find($juice)->depth)->toBe(1);
        expect(Category::find($juice)->path)->toContain("/{$drinks}/")->not->toContain("/{$food}/");
    });

    it('rejects moving a category under its own descendant', function (): void {
        [, $token, $companyId] = inventoryActor();

        $food = createCategory($token, $companyId, 'Food');
        $drinks = createCategory($token, $companyId, 'Drinks', $food);

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/inventory/categories/{$food}/move", ['parent_id' => $drinks])
            ->assertUnprocessable();
    });

    it('isolates categories by company', function (): void {
        [, $tokenA, $companyA] = inventoryActor();
        [, $tokenB, $companyB] = inventoryActor();

        createCategory($tokenA, $companyA, 'A-only');

        $this->withToken($tokenB)
            ->withHeader('X-Company-Id', (string) $companyB)
            ->getJson('/api/v1/inventory/categories')
            ->assertSuccessful()
            ->assertJsonCount(0, 'data.categories');
    });

    it('denies users without inventory.manage-products', function (): void {
        [, $token, $companyId] = inventoryActor();

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

        $this->withToken($memberToken)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/inventory/categories', ['name' => 'Sneaky'])
            ->assertForbidden();
    });
});
