<?php

use App\Models\User;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\Membership;
use App\Modules\Platform\Services\SettingsManager;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
});

function makeSettingsActor(bool $grantPermission = true): array
{
    $user = User::factory()->create();
    $company = Company::create([
        'name' => 'Settings Co',
        'slug' => 'settings-co-'.uniqid(),
        'status' => 'active',
    ]);
    $branch = Branch::create([
        'company_id' => $company->id,
        'name' => 'Main Branch',
        'code' => 'MAIN',
        'is_primary' => true,
        'status' => 'active',
    ]);
    Membership::create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'branch_id' => $branch->id,
        'role' => 'owner',
        'status' => 'active',
    ]);

    Permission::findOrCreate('platform.manage-settings', 'api');

    if ($grantPermission) {
        setPermissionsTeamId($company->id);
        $user->givePermissionTo('platform.manage-settings');
        setPermissionsTeamId(null);
    }

    return [$user, $company, $branch];
}

function settingsToken($user): string
{
    return test()->postJson('/api/v1/auth/login', [
        'login' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');
}

describe('Platform settings', function () {
    it('upserts and reads company-level settings', function (): void {
        [$user, $company] = makeSettingsActor();
        $token = settingsToken($user);

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->putJson('/api/v1/platform/settings', [
                'settings' => [
                    ['module' => 'inventory', 'key' => 'low_stock_threshold', 'value' => 10],
                ],
            ])
            ->assertSuccessful();

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/platform/settings?module=inventory')
            ->assertSuccessful()
            ->assertJsonStructure([
                'message',
                'data' => ['settings' => [['id', 'company_id', 'branch_id', 'module', 'key', 'value']]],
            ])
            ->assertJsonFragment(['key' => 'low_stock_threshold', 'value' => 10]);

        $this->assertDatabaseHas('settings', [
            'company_id' => $company->id,
            'branch_id' => null,
            'module' => 'inventory',
            'key' => 'low_stock_threshold',
        ]);
    });

    it('resolves a branch-level setting over the company-level value', function (): void {
        [$user, $company, $branch] = makeSettingsActor();
        $token = settingsToken($user);

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->putJson('/api/v1/platform/settings', [
                'settings' => [
                    ['module' => 'inventory', 'key' => 'low_stock_threshold', 'value' => 10],
                ],
            ])
            ->assertSuccessful();

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->putJson('/api/v1/platform/settings', [
                'settings' => [
                    ['module' => 'inventory', 'key' => 'low_stock_threshold', 'value' => 3, 'branch_id' => $branch->id],
                ],
            ])
            ->assertSuccessful();

        $manager = app(SettingsManager::class);

        expect($manager->get($company->id, 'inventory', 'low_stock_threshold', null, $branch->id))->toBe(3);
        expect($manager->get($company->id, 'inventory', 'low_stock_threshold'))->toBe(10);
    });

    it('denies users without platform.manage-settings', function (): void {
        [$user, $company] = makeSettingsActor(grantPermission: false);
        $token = settingsToken($user);

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->putJson('/api/v1/platform/settings', [
                'settings' => [
                    ['module' => 'inventory', 'key' => 'low_stock_threshold', 'value' => 1],
                ],
            ])
            ->assertForbidden();
    });
});
