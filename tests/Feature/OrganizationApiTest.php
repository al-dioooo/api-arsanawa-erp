<?php

use App\Models\User;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\Membership;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
});

describe('POST /api/v1/organization/companies', function () {
    it('creates a company with a primary branch and owner membership', function () {
        $user = User::factory()->create();

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.access_token');

        $response = $this->withToken($token)
            ->postJson('/api/v1/organization/companies', [
                'name' => 'SEKALORI Catering',
                'slug' => 'sekalori',
                'legal_name' => 'PT Sekalori Rasa Nusantara',
                'tax_identifier' => '01.234.567.8-999.000',
                'primary_branch_name' => 'SEKALORI HQ',
            ])
            ->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'company' => ['id', 'name', 'slug', 'legal_name', 'tax_identifier', 'status'],
                    'primary_branch' => ['id', 'company_id', 'name', 'code', 'is_primary', 'status'],
                    'membership' => ['id', 'company_id', 'user_id', 'branch_id', 'role', 'status'],
                ],
            ])
            ->assertJsonPath('data.company.name', 'SEKALORI Catering')
            ->assertJsonPath('data.primary_branch.name', 'SEKALORI HQ')
            ->assertJsonPath('data.membership.role', 'owner');

        $companyId = $response->json('data.company.id');

        $this->assertDatabaseHas('companies', [
            'id' => $companyId,
            'slug' => 'sekalori',
            'created_by' => $user->id,
        ]);

        $this->assertDatabaseHas('branches', [
            'company_id' => $companyId,
            'name' => 'SEKALORI HQ',
            'is_primary' => true,
        ]);

        $this->assertDatabaseHas('memberships', [
            'company_id' => $companyId,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('roles', [
            'team_id' => $companyId,
            'name' => 'company-owner',
            'guard_name' => 'api',
        ]);

        setPermissionsTeamId($companyId);

        expect($user->fresh()->can('organization.manage-members'))->toBeTrue();
    });

    it('rejects duplicate company slugs', function () {
        Company::create([
            'name' => 'Existing Company',
            'slug' => 'existing-company',
            'status' => 'active',
        ]);

        $user = User::factory()->create();

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.access_token');

        $this->withToken($token)
            ->postJson('/api/v1/organization/companies', [
                'name' => 'Another Company',
                'slug' => 'existing-company',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    });
});

describe('GET /api/v1/organization/companies', function () {
    it('lists only companies where the user has an active membership', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownedCompany = Company::create(['name' => 'Owned Company', 'slug' => 'owned-company', 'status' => 'active']);
        $otherCompany = Company::create(['name' => 'Other Company', 'slug' => 'other-company', 'status' => 'active']);
        $suspendedCompany = Company::create(['name' => 'Suspended Membership Company', 'slug' => 'suspended-membership-company', 'status' => 'active']);

        Membership::create([
            'company_id' => $ownedCompany->id,
            'user_id' => $user->id,
            'role' => 'member',
            'status' => 'active',
        ]);

        Membership::create([
            'company_id' => $otherCompany->id,
            'user_id' => $otherUser->id,
            'role' => 'member',
            'status' => 'active',
        ]);

        Membership::create([
            'company_id' => $suspendedCompany->id,
            'user_id' => $user->id,
            'role' => 'member',
            'status' => 'suspended',
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.access_token');

        $this->withToken($token)
            ->getJson('/api/v1/organization/companies')
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.companies')
            ->assertJsonPath('data.companies.0.company.id', $ownedCompany->id)
            ->assertJsonPath('data.companies.0.membership.status', 'active');
    });

    it('lists active companies without memberships for developer users', function () {
        $developer = User::factory()->create(['is_developer' => true]);
        $activeCompany = Company::create(['name' => 'Developer Visible Company', 'slug' => 'developer-visible-company', 'status' => 'active']);
        $inactiveCompany = Company::create(['name' => 'Inactive Company', 'slug' => 'inactive-company', 'status' => 'inactive']);

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $developer->email,
            'password' => 'password',
        ])->json('data.access_token');

        $this->withToken($token)
            ->getJson('/api/v1/organization/companies')
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.companies')
            ->assertJsonPath('data.companies.0.company.id', $activeCompany->id)
            ->assertJsonPath('data.companies.0.membership', null);

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $activeCompany->id)
            ->getJson('/api/v1/organization/context')
            ->assertSuccessful()
            ->assertJsonPath('data.company.id', $activeCompany->id)
            ->assertJsonPath('data.membership', null);

        expect($inactiveCompany->exists)->toBeTrue();
    });
});

describe('GET /api/v1/organization/context', function () {
    it('returns the active company selected by the X-Company-Id header', function () {
        $user = User::factory()->create();

        $firstCompany = Company::create(['name' => 'First Company', 'slug' => 'first-company', 'status' => 'active']);
        $secondCompany = Company::create(['name' => 'Second Company', 'slug' => 'second-company', 'status' => 'active']);

        Membership::create([
            'company_id' => $firstCompany->id,
            'user_id' => $user->id,
            'role' => 'member',
            'status' => 'active',
        ]);

        Membership::create([
            'company_id' => $secondCompany->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'status' => 'active',
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.access_token');

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $secondCompany->id)
            ->getJson('/api/v1/organization/context')
            ->assertSuccessful()
            ->assertJsonPath('data.company.id', $secondCompany->id)
            ->assertJsonPath('data.membership.role', 'admin');
    });

    it('returns 403 when the requested company is not one of the user memberships', function () {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Unrelated Company', 'slug' => 'unrelated-company', 'status' => 'active']);

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.access_token');

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/organization/context')
            ->assertForbidden()
            ->assertJsonPath('message', 'Company access denied.');
    });
});

describe('POST /api/v1/organization/companies/{company}/branches', function () {
    it('allows a company owner to create a branch', function () {
        $owner = User::factory()->create();

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $owner->email,
            'password' => 'password',
        ])->json('data.access_token');

        $companyId = $this->withToken($token)
            ->postJson('/api/v1/organization/companies', [
                'name' => 'Branch Owner Company',
                'slug' => 'branch-owner-company',
            ])
            ->json('data.company.id');

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/organization/companies/{$companyId}/branches", [
                'name' => 'South Kitchen',
                'code' => 'SOUTH',
            ])
            ->assertCreated()
            ->assertJsonPath('data.branch.name', 'South Kitchen')
            ->assertJsonPath('data.branch.code', 'SOUTH');

        $this->assertDatabaseHas('branches', [
            'company_id' => $companyId,
            'name' => 'South Kitchen',
            'code' => 'SOUTH',
        ]);
    });

    it('returns 403 when an active member lacks branch management permission', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $ownerToken = $this->postJson('/api/v1/auth/login', [
            'login' => $owner->email,
            'password' => 'password',
        ])->json('data.access_token');

        $companyId = $this->withToken($ownerToken)
            ->postJson('/api/v1/organization/companies', [
                'name' => 'Restricted Branch Company',
                'slug' => 'restricted-branch-company',
            ])
            ->json('data.company.id');

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
            ->postJson("/api/v1/organization/companies/{$companyId}/branches", [
                'name' => 'Unauthorized Branch',
            ])
            ->assertForbidden();
    });
});

describe('POST /api/v1/organization/companies/{company}/memberships', function () {
    it('allows a company owner to add an existing user as a member', function () {
        $owner = User::factory()->create();
        $newMember = User::factory()->create();

        $ownerToken = $this->postJson('/api/v1/auth/login', [
            'login' => $owner->email,
            'password' => 'password',
        ])->json('data.access_token');

        $companyResponse = $this->withToken($ownerToken)
            ->postJson('/api/v1/organization/companies', [
                'name' => 'Membership Company',
                'slug' => 'membership-company',
            ]);

        $companyId = $companyResponse->json('data.company.id');
        $branchId = $companyResponse->json('data.primary_branch.id');

        $this->withToken($ownerToken)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/organization/companies/{$companyId}/memberships", [
                'user_id' => $newMember->id,
                'branch_id' => $branchId,
                'role' => 'member',
            ])
            ->assertCreated()
            ->assertJsonPath('data.membership.user_id', $newMember->id)
            ->assertJsonPath('data.membership.branch_id', $branchId)
            ->assertJsonPath('data.membership.status', 'active');

        $this->assertDatabaseHas('memberships', [
            'company_id' => $companyId,
            'user_id' => $newMember->id,
            'branch_id' => $branchId,
            'role' => 'member',
            'status' => 'active',
        ]);
    });
});

describe('PUT /api/v1/organization/companies/{company}/entitlements', function () {
    it('upserts module entitlements and drives the module registry from active company permissions', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        Permission::findOrCreate('inventory.view', 'api');
        Permission::findOrCreate('pos.view', 'api');

        $ownerToken = $this->postJson('/api/v1/auth/login', [
            'login' => $owner->email,
            'password' => 'password',
        ])->json('data.access_token');

        $companyId = $this->withToken($ownerToken)
            ->postJson('/api/v1/organization/companies', [
                'name' => 'Entitlement Company',
                'slug' => 'entitlement-company',
            ])
            ->json('data.company.id');

        Membership::create([
            'company_id' => $companyId,
            'user_id' => $member->id,
            'role' => 'member',
            'status' => 'active',
        ]);

        setPermissionsTeamId($companyId);
        $member->givePermissionTo('inventory.view');
        setPermissionsTeamId(null);

        $this->withToken($ownerToken)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->putJson("/api/v1/organization/companies/{$companyId}/entitlements", [
                'modules' => [
                    ['module' => 'inventory', 'is_enabled' => true],
                    ['module' => 'pos', 'is_enabled' => false],
                ],
            ])
            ->assertSuccessful()
            ->assertJsonCount(2, 'data.entitlements');

        $this->withToken($ownerToken)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/modules')
            ->assertSuccessful()
            ->assertJsonPath('data.company.id', $companyId)
            ->assertJsonPath('data.enabled', ['inventory'])
            ->assertJsonPath('data.available', ['pos']);

        $memberToken = $this->postJson('/api/v1/auth/login', [
            'login' => $member->email,
            'password' => 'password',
        ])->json('data.access_token');

        $this->withToken($memberToken)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/modules')
            ->assertSuccessful()
            ->assertJsonPath('data.enabled', ['inventory'])
            ->assertJsonPath('data.available', []);
    });
});
