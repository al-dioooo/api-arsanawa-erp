<?php

use App\Models\User;
use App\Modules\Organization\Models\Membership;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
});

describe('Inventory units of measure', function () {
    it('creates, lists, updates and deletes a unit of measure', function (): void {
        [, $token, $companyId] = inventoryActor();

        $uomId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/inventory/units-of-measure', ['name' => 'Kilogram', 'code' => 'kg'])
            ->assertCreated()
            ->assertJsonPath('data.unit.code', 'kg')
            ->json('data.unit.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/inventory/units-of-measure')
            ->assertSuccessful()
            ->assertJsonFragment(['code' => 'kg']);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->patchJson("/api/v1/inventory/units-of-measure/{$uomId}", ['name' => 'Kilogramme'])
            ->assertSuccessful()
            ->assertJsonPath('data.unit.name', 'Kilogramme');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->deleteJson("/api/v1/inventory/units-of-measure/{$uomId}")
            ->assertSuccessful();

        $this->assertDatabaseMissing('units_of_measure', ['id' => $uomId]);
    });

    it('rejects a duplicate code within a company', function (): void {
        [, $token, $companyId] = inventoryActor();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/inventory/units-of-measure', ['name' => 'Piece', 'code' => 'pcs'])
            ->assertCreated();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/inventory/units-of-measure', ['name' => 'Pieces', 'code' => 'pcs'])
            ->assertUnprocessable();
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
            ->postJson('/api/v1/inventory/units-of-measure', ['name' => 'Kilogram', 'code' => 'kg'])
            ->assertForbidden();
    });
});
