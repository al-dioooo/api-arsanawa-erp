<?php

use App\Models\User;
use App\Modules\Organization\Models\Membership;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
});

describe('Inventory variant groups', function () {
    it('creates, lists, shows, updates and deletes a variant group', function (): void {
        [, $token, $companyId] = inventoryActor();
        $unitId = createUnit($token, $companyId, 'PAX', 'Pax');

        $groupId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/inventory/variant-groups', [
                'name' => 'Package Size',
                'code' => 'package-size',
                'unit_of_measure_id' => $unitId,
                'description' => 'Serving package size',
            ])
            ->assertCreated()
            ->assertJsonPath('data.variant_group.name', 'Package Size')
            ->assertJsonPath('data.variant_group.unit.id', $unitId)
            ->json('data.variant_group.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/inventory/variant-groups')
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.variant_groups')
            ->assertJsonPath('data.variant_groups.0.code', 'package-size');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/variant-groups/{$groupId}")
            ->assertSuccessful()
            ->assertJsonPath('data.variant_group.description', 'Serving package size');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->patchJson("/api/v1/inventory/variant-groups/{$groupId}", [
                'name' => 'Package Volume',
                'is_active' => false,
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.variant_group.name', 'Package Volume')
            ->assertJsonPath('data.variant_group.is_active', false);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->deleteJson("/api/v1/inventory/variant-groups/{$groupId}")
            ->assertSuccessful();

        $this->assertDatabaseMissing('variant_groups', ['id' => $groupId]);
    });

    it('rejects duplicate variant group codes within a company', function (): void {
        [, $token, $companyId] = inventoryActor();
        $unitId = createUnit($token, $companyId, 'PCS');

        $payload = [
            'name' => 'Spice Level',
            'code' => 'spice',
            'unit_of_measure_id' => $unitId,
        ];

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/inventory/variant-groups', $payload)
            ->assertCreated();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/inventory/variant-groups', $payload)
            ->assertUnprocessable();
    });

    it('isolates variant groups by company', function (): void {
        [, $tokenA, $companyA] = inventoryActor();
        [, $tokenB, $companyB] = inventoryActor();
        $unitA = createUnit($tokenA, $companyA, 'PCS');
        $unitB = createUnit($tokenB, $companyB, 'PCS');

        $this->withToken($tokenA)->withHeader('X-Company-Id', (string) $companyA)
            ->postJson('/api/v1/inventory/variant-groups', [
                'name' => 'Shared',
                'code' => 'shared',
                'unit_of_measure_id' => $unitA,
            ])
            ->assertCreated();

        $this->withToken($tokenB)->withHeader('X-Company-Id', (string) $companyB)
            ->postJson('/api/v1/inventory/variant-groups', [
                'name' => 'Shared',
                'code' => 'shared',
                'unit_of_measure_id' => $unitB,
            ])
            ->assertCreated();

        $this->withToken($tokenB)->withHeader('X-Company-Id', (string) $companyB)
            ->getJson('/api/v1/inventory/variant-groups')
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.variant_groups');
    });

    it('denies users without inventory.manage-products', function (): void {
        [, $token, $companyId] = inventoryActor();
        $unitId = createUnit($token, $companyId, 'PCS');

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
            ->postJson('/api/v1/inventory/variant-groups', [
                'name' => 'Nope',
                'code' => 'nope',
                'unit_of_measure_id' => $unitId,
            ])
            ->assertForbidden();
    });
});
