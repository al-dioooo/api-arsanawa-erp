<?php

use App\Models\User;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\Membership;
use Database\Seeders\DatabaseSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
});

describe('Database seeder', function (): void {
    it('seeds Alice as developer and a separate SEKALORI owner demo user', function (): void {
        $this->seed(DatabaseSeeder::class);

        $alice = User::query()->where('username', 'aliceevr')->first();
        $sekaloriOwner = User::query()->where('username', 'sekalori')->first();
        $company = Company::query()->where('slug', 'sekalori')->first();

        expect($alice)->not->toBeNull()
            ->and($alice->is_developer)->toBeTrue()
            ->and($sekaloriOwner)->not->toBeNull()
            ->and($sekaloriOwner->is_developer)->toBeFalse()
            ->and($company)->not->toBeNull();

        $this->assertDatabaseHas('memberships', [
            'company_id' => $company->id,
            'user_id' => $sekaloriOwner->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $this->assertDatabaseMissing('memberships', [
            'company_id' => $company->id,
            'user_id' => $alice->id,
            'role' => 'owner',
        ]);

        setPermissionsTeamId($company->id);

        expect($sekaloriOwner->fresh()->can('organization.manage-api-keys'))->toBeTrue();

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => 'owner@sekalori.test',
            'password' => 'sekalori1234',
        ])->assertSuccessful()->json('data.access_token');

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->getJson("/api/v1/organization/companies/{$company->id}/api-keys")
            ->assertSuccessful();

        $aliceToken = $this->postJson('/api/v1/auth/login', [
            'login' => 'hello@al.is-a.dev',
            'password' => 'aldio1234',
        ])->assertSuccessful()
            ->assertJsonPath('data.user.is_developer', true)
            ->json('data.access_token');

        $this->withToken($aliceToken)
            ->getJson('/api/v1/organization/companies')
            ->assertSuccessful()
            ->assertJsonPath('data.companies.0.company.slug', 'sekalori')
            ->assertJsonPath('data.companies.0.membership', null);

        expect(Membership::query()
            ->where('company_id', $company->id)
            ->where('role', 'owner')
            ->count())->toBe(1);
    });
});
