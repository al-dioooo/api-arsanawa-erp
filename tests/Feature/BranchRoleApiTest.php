<?php

use App\Models\User;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\BranchAssignment;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\Membership;
use App\Modules\Organization\Services\BranchPermission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
});

/**
 * @return array{0: User, 1: string, 2: int, 3: int}
 */
function branchRoleSetup(): array
{
    $owner = User::factory()->create();

    $token = test()->postJson('/api/v1/auth/login', [
        'login' => $owner->email,
        'password' => 'password',
    ])->json('data.access_token');

    $company = test()->withToken($token)
        ->postJson('/api/v1/organization/companies', [
            'name' => 'Branch Role Co '.uniqid(),
        ])
        ->json('data');

    return [$owner, $token, $company['company']['id'], $company['primary_branch']['id']];
}

function createBranchRole(string $token, int $companyId, string $name, array $permissions): int
{
    return test()->withToken($token)
        ->withHeader('X-Company-Id', (string) $companyId)
        ->postJson("/api/v1/organization/companies/{$companyId}/roles", [
            'name' => $name,
            'permissions' => $permissions,
        ])
        ->json('data.role.id');
}

function activeMember(int $companyId): User
{
    $member = User::factory()->create();

    Membership::create([
        'company_id' => $companyId,
        'user_id' => $member->id,
        'role' => 'member',
        'status' => 'active',
    ]);

    return $member;
}

describe('Branch role assignments', function () {
    it('assigns a branch role and lists it', function (): void {
        [, $token, $companyId, $branchId] = branchRoleSetup();
        $member = activeMember($companyId);
        $roleId = createBranchRole($token, $companyId, 'Branch Staff', ['identity.view']);

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/organization/companies/{$companyId}/branches/{$branchId}/assignments", [
                'user_id' => $member->id,
                'role_id' => $roleId,
            ])
            ->assertCreated()
            ->assertJsonPath('data.assignment.user_id', $member->id)
            ->assertJsonPath('data.assignment.role_id', $roleId);

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/organization/companies/{$companyId}/branches/{$branchId}/assignments")
            ->assertSuccessful()
            ->assertJsonFragment(['user_id' => $member->id]);

        $this->assertDatabaseHas('branch_user', [
            'branch_id' => $branchId,
            'user_id' => $member->id,
            'role_id' => $roleId,
        ]);
    });

    it('enforces branch-scoped permissions via BranchPermission', function (): void {
        [, $token, $companyId, $branchAId] = branchRoleSetup();

        $branchBId = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/organization/companies/{$companyId}/branches", [
                'name' => 'Branch B',
                'code' => 'BR-B',
            ])
            ->json('data.branch.id');

        $member = activeMember($companyId);
        $roleId = createBranchRole($token, $companyId, 'Branch Staff', ['identity.view']);

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/organization/companies/{$companyId}/branches/{$branchAId}/assignments", [
                'user_id' => $member->id,
                'role_id' => $roleId,
            ])
            ->assertCreated();

        $branchPermission = app(BranchPermission::class);

        expect($branchPermission->userCanInBranch($member->fresh(), 'identity.view', $branchAId))->toBeTrue();
        expect($branchPermission->userCanInBranch($member->fresh(), 'identity.view', $branchBId))->toBeFalse();
    });

    it('exposes the active branch and assignments in the organization context', function (): void {
        [, $token, $companyId, $branchId] = branchRoleSetup();

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->getJson('/api/v1/organization/context')
            ->assertSuccessful()
            ->assertJsonPath('data.active_branch_id', $branchId)
            ->assertJsonStructure(['data' => ['branch_assignments']]);
    });

    it('rejects an X-Branch-Id that belongs to another company', function (): void {
        [, $token, $companyId] = branchRoleSetup();

        $otherCompany = Company::create([
            'name' => 'Other Co',
            'slug' => 'other-co-'.uniqid(),
            'status' => 'active',
        ]);
        $foreignBranch = Branch::create([
            'company_id' => $otherCompany->id,
            'name' => 'Foreign Branch',
            'code' => 'FRG',
            'is_primary' => true,
            'status' => 'active',
        ]);

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $foreignBranch->id)
            ->getJson('/api/v1/organization/context')
            ->assertForbidden()
            ->assertJsonPath('message', 'Branch access denied.');
    });

    it('updates an existing assignment instead of duplicating it', function (): void {
        [, $token, $companyId, $branchId] = branchRoleSetup();
        $member = activeMember($companyId);
        $roleOne = createBranchRole($token, $companyId, 'Role One', ['identity.view']);
        $roleTwo = createBranchRole($token, $companyId, 'Role Two', ['organization.view']);

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/organization/companies/{$companyId}/branches/{$branchId}/assignments", [
                'user_id' => $member->id,
                'role_id' => $roleOne,
            ])
            ->assertCreated();

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/organization/companies/{$companyId}/branches/{$branchId}/assignments", [
                'user_id' => $member->id,
                'role_id' => $roleTwo,
            ])
            ->assertCreated();

        expect(
            BranchAssignment::query()
                ->where('branch_id', $branchId)
                ->where('user_id', $member->id)
                ->count(),
        )->toBe(1);

        $this->assertDatabaseHas('branch_user', [
            'branch_id' => $branchId,
            'user_id' => $member->id,
            'role_id' => $roleTwo,
        ]);
    });

    it('revokes a branch assignment', function (): void {
        [, $token, $companyId, $branchId] = branchRoleSetup();
        $member = activeMember($companyId);
        $roleId = createBranchRole($token, $companyId, 'Branch Staff', ['identity.view']);

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/organization/companies/{$companyId}/branches/{$branchId}/assignments", [
                'user_id' => $member->id,
                'role_id' => $roleId,
            ])
            ->assertCreated();

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->deleteJson("/api/v1/organization/companies/{$companyId}/branches/{$branchId}/assignments/{$member->id}")
            ->assertSuccessful();

        $this->assertDatabaseMissing('branch_user', [
            'branch_id' => $branchId,
            'user_id' => $member->id,
        ]);
    });

    it('denies users without organization.manage-members', function (): void {
        [, $token, $companyId, $branchId] = branchRoleSetup();
        $member = activeMember($companyId);
        $roleId = createBranchRole($token, $companyId, 'Branch Staff', ['identity.view']);

        $memberToken = $this->postJson('/api/v1/auth/login', [
            'login' => $member->email,
            'password' => 'password',
        ])->json('data.access_token');

        $this->withToken($memberToken)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/organization/companies/{$companyId}/branches/{$branchId}/assignments", [
                'user_id' => $member->id,
                'role_id' => $roleId,
            ])
            ->assertForbidden();
    });
});
