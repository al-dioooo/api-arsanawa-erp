<?php

use App\Models\User;
use App\Modules\Identity\Models\UserProfile;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\Membership;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

describe('GET /api/v1/identity/profile', function () {
    it('returns the authenticated user own profile', function () {
        $user = User::factory()->create();

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.access_token');

        $this->withToken($token)
            ->getJson('/api/v1/identity/profile')
            ->assertSuccessful()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'name',
                    'username',
                    'email',
                    'profile' => [
                        'display_name',
                        'avatar',
                        'locale',
                        'timezone',
                    ],
                    'status' => [
                        'status',
                    ],
                ],
            ])
            ->assertJsonPath('data.id', $user->id);
    });

    it('returns profile data when user_profile row exists', function () {
        $user = User::factory()->create();

        UserProfile::create([
            'user_id' => $user->id,
            'display_name' => 'Alice Display',
            'avatar' => 'https://example.com/avatar.jpg',
            'locale' => 'en',
            'timezone' => 'UTC',
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.access_token');

        $this->withToken($token)
            ->getJson('/api/v1/identity/profile')
            ->assertSuccessful()
            ->assertJsonPath('data.profile.display_name', 'Alice Display')
            ->assertJsonPath('data.profile.locale', 'en');
    });

    it('returns 401 for unauthenticated requests', function () {
        $this->getJson('/api/v1/identity/profile')
            ->assertUnauthorized();
    });
});

describe('PATCH /api/v1/identity/profile', function () {
    it('updates the authenticated user own profile', function () {
        $user = User::factory()->create();

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.access_token');

        $this->withToken($token)
            ->patchJson('/api/v1/identity/profile', [
                'display_name' => 'Bob Updated',
                'locale' => 'ku',
                'timezone' => 'Asia/Baghdad',
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.profile.display_name', 'Bob Updated')
            ->assertJsonPath('data.profile.locale', 'ku')
            ->assertJsonPath('data.profile.timezone', 'Asia/Baghdad');

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->id,
            'display_name' => 'Bob Updated',
            'locale' => 'ku',
        ]);
    });

    it('creates a profile row if none exists yet', function () {
        $user = User::factory()->create();

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.access_token');

        $this->assertDatabaseMissing('user_profiles', ['user_id' => $user->id]);

        $this->withToken($token)
            ->patchJson('/api/v1/identity/profile', [
                'display_name' => 'New User',
            ])
            ->assertSuccessful();

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->id,
            'display_name' => 'New User',
        ]);
    });

    it('rejects invalid locale values', function () {
        $user = User::factory()->create();

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.access_token');

        $this->withToken($token)
            ->patchJson('/api/v1/identity/profile', [
                'locale' => str_repeat('x', 11),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['locale']);
    });

    it('returns 401 for unauthenticated requests', function () {
        $this->patchJson('/api/v1/identity/profile', ['display_name' => 'Hacker'])
            ->assertUnauthorized();
    });
});

describe('GET /api/v1/identity/users/{id}', function () {
    it('returns a user profile when the requester has identity.view permission', function () {
        Permission::create(['name' => 'identity.view', 'guard_name' => 'api']);

        $admin = User::factory()->create();
        $company = Company::create(['name' => 'Identity Admin Company', 'slug' => 'identity-admin-company', 'status' => 'active']);

        Membership::create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'role' => 'admin',
            'status' => 'active',
        ]);

        setPermissionsTeamId($company->id);
        $admin->givePermissionTo('identity.view');
        setPermissionsTeamId(null);

        $target = User::factory()->create();
        Membership::create([
            'company_id' => $company->id,
            'user_id' => $target->id,
            'role' => 'member',
            'status' => 'active',
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $admin->email,
            'password' => 'password',
        ])->json('data.access_token');

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->getJson("/api/v1/identity/users/{$target->id}")
            ->assertSuccessful()
            ->assertJsonStructure([
                'message',
                'data' => ['id', 'name', 'username', 'email', 'profile', 'status'],
            ])
            ->assertJsonPath('data.id', $target->id);
    });

    it('returns 404 for a user outside the requester active company (tenant isolation)', function () {
        Permission::create(['name' => 'identity.view', 'guard_name' => 'api']);

        $admin = User::factory()->create();
        $companyA = Company::create(['name' => 'Company A', 'slug' => 'company-a-iso', 'status' => 'active']);
        Membership::create([
            'company_id' => $companyA->id,
            'user_id' => $admin->id,
            'role' => 'admin',
            'status' => 'active',
        ]);

        setPermissionsTeamId($companyA->id);
        $admin->givePermissionTo('identity.view');
        setPermissionsTeamId(null);

        // Target belongs only to a different company.
        $companyB = Company::create(['name' => 'Company B', 'slug' => 'company-b-iso', 'status' => 'active']);
        $target = User::factory()->create();
        Membership::create([
            'company_id' => $companyB->id,
            'user_id' => $target->id,
            'role' => 'member',
            'status' => 'active',
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $admin->email,
            'password' => 'password',
        ])->json('data.access_token');

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyA->id)
            ->getJson("/api/v1/identity/users/{$target->id}")
            ->assertNotFound();
    });

    it('returns 403 when the requester lacks identity.view permission', function () {
        $requester = User::factory()->create();
        $target = User::factory()->create();

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $requester->email,
            'password' => 'password',
        ])->json('data.access_token');

        $this->withToken($token)
            ->getJson("/api/v1/identity/users/{$target->id}")
            ->assertForbidden();
    });

    it('returns 404 for a non-existent user', function () {
        Permission::create(['name' => 'identity.view', 'guard_name' => 'api']);

        $admin = User::factory()->create();
        $company = Company::create(['name' => 'Identity Missing User Company', 'slug' => 'identity-missing-user-company', 'status' => 'active']);

        Membership::create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'role' => 'admin',
            'status' => 'active',
        ]);

        setPermissionsTeamId($company->id);
        $admin->givePermissionTo('identity.view');
        setPermissionsTeamId(null);

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $admin->email,
            'password' => 'password',
        ])->json('data.access_token');

        $this->withToken($token)
            ->getJson('/api/v1/identity/users/99999999')
            ->assertNotFound();
    });

    it('returns 401 for unauthenticated requests', function () {
        $this->getJson('/api/v1/identity/users/1')
            ->assertUnauthorized();
    });
});
