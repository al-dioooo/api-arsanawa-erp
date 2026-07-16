<?php

use App\Models\User;
use App\Modules\Organization\Models\Membership;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
});

function roleOwner(): array
{
    $user = User::factory()->create();

    $token = test()->postJson('/api/v1/auth/login', [
        'login' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    $companyId = test()->withToken($token)
        ->postJson('/api/v1/organization/companies', [
            'name' => 'Role Co '.uniqid(),
        ])
        ->json('data.company.id');

    return [$user, $token, $companyId];
}

describe('Role management', function () {
    it('exposes the permission catalog', function (): void {
        [, $token, $companyId] = roleOwner();

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/organization/permissions')
            ->assertSuccessful()
            ->assertJsonPath('data.permissions.organization.label', 'Organization')
            ->assertJsonFragment(['key' => 'organization.manage-roles']);
    });

    it('forbids the permission catalog for a member without manage-roles', function (): void {
        [, , $companyId] = roleOwner();

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
            ->getJson('/api/v1/organization/permissions')
            ->assertForbidden();
    });

    it('creates and lists a custom role', function (): void {
        [, $token, $companyId] = roleOwner();

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/organization/companies/{$companyId}/roles", [
                'name' => 'Catering Supervisor',
                'permissions' => ['identity.view'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.role.name', 'Catering Supervisor')
            ->assertJsonPath('data.role.is_builtin', false)
            ->assertJsonPath('data.role.permissions', ['identity.view']);

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/organization/companies/{$companyId}/roles")
            ->assertSuccessful()
            ->assertJsonFragment(['name' => 'Catering Supervisor']);
    });

    it('shows a single role with its permissions', function (): void {
        [, $token, $companyId] = roleOwner();

        $roleId = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/organization/companies/{$companyId}/roles", [
                'name' => 'Auditor',
                'permissions' => ['identity.view'],
            ])
            ->json('data.role.id');

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/organization/companies/{$companyId}/roles/{$roleId}")
            ->assertSuccessful()
            ->assertJsonPath('data.role.name', 'Auditor')
            ->assertJsonPath('data.role.is_builtin', false)
            ->assertJsonPath('data.role.permissions', ['identity.view']);
    });

    it('updates a custom role', function (): void {
        [, $token, $companyId] = roleOwner();

        $roleId = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/organization/companies/{$companyId}/roles", [
                'name' => 'Supervisor',
                'permissions' => ['identity.view'],
            ])
            ->json('data.role.id');

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->putJson("/api/v1/organization/companies/{$companyId}/roles/{$roleId}", [
                'name' => 'Senior Supervisor',
                'permissions' => ['identity.view', 'organization.view'],
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.role.name', 'Senior Supervisor')
            ->assertJsonPath('data.role.permissions', ['identity.view', 'organization.view']);
    });

    it('rejects unknown permission keys', function (): void {
        [, $token, $companyId] = roleOwner();

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/organization/companies/{$companyId}/roles", [
                'name' => 'Broken Role',
                'permissions' => ['does.not.exist'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions.0']);
    });

    it('cannot delete a built-in role', function (): void {
        [, $token, $companyId] = roleOwner();

        setPermissionsTeamId($companyId);
        $ownerRole = Role::query()
            ->where('team_id', $companyId)
            ->where('name', 'company-owner')
            ->firstOrFail();
        setPermissionsTeamId(null);

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->deleteJson("/api/v1/organization/companies/{$companyId}/roles/{$ownerRole->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('roles', [
            'id' => $ownerRole->id,
            'name' => 'company-owner',
        ]);
    });

    it('denies users without organization.manage-roles', function (): void {
        [, , $companyId] = roleOwner();

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
            ->postJson("/api/v1/organization/companies/{$companyId}/roles", [
                'name' => 'Sneaky Role',
                'permissions' => [],
            ])
            ->assertForbidden();
    });
});
