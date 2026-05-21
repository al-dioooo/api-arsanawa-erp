<?php

use App\Models\User;
use App\Modules\Organization\Models\Membership;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
});

describe('Inventory brands', function () {
    it('creates, lists, updates and deletes a brand', function (): void {
        [, $token, $companyId] = inventoryActor();

        $brandId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/inventory/brands', ['name' => 'Indofood'])
            ->assertCreated()
            ->assertJsonPath('data.brand.name', 'Indofood')
            ->json('data.brand.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/inventory/brands')
            ->assertSuccessful()
            ->assertJsonFragment(['name' => 'Indofood']);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->patchJson("/api/v1/inventory/brands/{$brandId}", ['name' => 'Indofood Sukses'])
            ->assertSuccessful()
            ->assertJsonPath('data.brand.name', 'Indofood Sukses');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->deleteJson("/api/v1/inventory/brands/{$brandId}")
            ->assertSuccessful();

        $this->assertDatabaseMissing('brands', ['id' => $brandId]);
    });

    it('rejects a duplicate brand name within a company', function (): void {
        [, $token, $companyId] = inventoryActor();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/inventory/brands', ['name' => 'Dup'])->assertCreated();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/inventory/brands', ['name' => 'Dup'])->assertUnprocessable();
    });

    it('isolates brands by company', function (): void {
        [, $tokenA, $companyA] = inventoryActor();
        [, $tokenB, $companyB] = inventoryActor();

        $this->withToken($tokenA)->withHeader('X-Company-Id', (string) $companyA)
            ->postJson('/api/v1/inventory/brands', ['name' => 'Shared'])->assertCreated();

        $this->withToken($tokenB)->withHeader('X-Company-Id', (string) $companyB)
            ->postJson('/api/v1/inventory/brands', ['name' => 'Shared'])->assertCreated();

        $this->withToken($tokenB)->withHeader('X-Company-Id', (string) $companyB)
            ->getJson('/api/v1/inventory/brands')
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.brands');
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

        $this->withToken($memberToken)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/inventory/brands', ['name' => 'Nope'])
            ->assertForbidden();
    });
});
