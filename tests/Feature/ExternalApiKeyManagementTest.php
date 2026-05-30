<?php

use App\Models\User;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\Membership;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
});

function loginForApiKeyTest(User $user): string
{
    return test()->postJson('/api/v1/auth/login', [
        'login' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');
}

function grantDeveloperRole(User $user): void
{
    $user->forceFill(['is_developer' => true])->save();
    $user->refresh();
}

function createOwnedCompanyForApiKeyTest(User $owner, string $name = 'API Key Company'): array
{
    $token = loginForApiKeyTest($owner);

    $data = test()->withToken($token)
        ->postJson('/api/v1/organization/companies', ['name' => $name.' '.uniqid()])
        ->assertCreated()
        ->json('data');

    return [$token, $data['company']['id'], $data['primary_branch']['id']];
}

describe('Developer organization access', function (): void {
    it('developer can access any company context', function (): void {
        $developer = User::factory()->create();
        $company = Company::create([
            'name' => 'Developer Visible Company',
            'slug' => 'developer-visible-company',
            'status' => 'active',
        ]);

        grantDeveloperRole($developer);

        $this->withToken(loginForApiKeyTest($developer))
            ->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/organization/context')
            ->assertSuccessful()
            ->assertJsonPath('data.company.id', $company->id)
            ->assertJsonPath('data.membership', null);
    });

    it('developer can manage organization api keys', function (): void {
        $developer = User::factory()->create();
        $outsider = User::factory()->create();
        $company = Company::create([
            'name' => 'Developer Managed Company',
            'slug' => 'developer-managed-company',
            'status' => 'active',
        ]);

        grantDeveloperRole($developer);

        $this->withToken(loginForApiKeyTest($developer))
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/organization/companies/{$company->id}/api-keys", [
                'name' => 'SEKALORI Landing Page',
                'source_channel' => 'Landing Page',
            ])
            ->assertCreated()
            ->assertJsonPath('data.api_key.company_id', $company->id)
            ->assertJsonPath('data.api_key.source_channel', 'Landing Page')
            ->assertJsonStructure([
                'data' => [
                    'plain_text_key',
                    'api_key' => ['id', 'company_id', 'name', 'token_prefix', 'source_channel', 'expires_at', 'revoked_at'],
                ],
            ]);

        $this->withToken(loginForApiKeyTest($outsider))
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/organization/companies/{$company->id}/api-keys", [
                'name' => 'Outsider Key',
                'source_channel' => 'Landing Page',
            ])
            ->assertForbidden();
    });

    it('developer can access module data for any company', function (): void {
        $owner = User::factory()->create();
        [$ownerToken, $companyId] = createOwnedCompanyForApiKeyTest($owner, 'Developer Inventory Company');
        $uom = createUnit($ownerToken, $companyId, 'dev-pcs');

        createProduct($ownerToken, $companyId, [
            'name' => 'Developer Visible Product',
            'base_uom_id' => $uom,
            'variants' => [['sku' => 'DEV-VISIBLE']],
        ]);

        $developer = User::factory()->create();
        grantDeveloperRole($developer);

        $this->withToken(loginForApiKeyTest($developer))
            ->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/inventory/products')
            ->assertSuccessful()
            ->assertJsonPath('data.products.0.name', 'Developer Visible Product');
    });
});

describe('Organization API key management', function (): void {
    it('creates lists shows and revokes organization api keys', function (): void {
        $owner = User::factory()->create();
        [$ownerToken, $companyId] = createOwnedCompanyForApiKeyTest($owner, 'Owner API Key Company');

        $createResponse = $this->withToken($ownerToken)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/organization/companies/{$companyId}/api-keys", [
                'name' => 'SEKALORI Landing Page',
                'source_channel' => 'Landing Page',
                'expires_at' => null,
            ])
            ->assertCreated()
            ->assertJsonPath('data.api_key.name', 'SEKALORI Landing Page')
            ->assertJsonPath('data.api_key.source_channel', 'Landing Page')
            ->assertJsonPath('data.api_key.expires_at', null)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'plain_text_key',
                    'api_key' => ['id', 'company_id', 'name', 'token_prefix', 'source_channel', 'last_used_at', 'expires_at', 'revoked_at'],
                ],
            ]);

        $plainTextKey = $createResponse->json('data.plain_text_key');
        $apiKeyId = $createResponse->json('data.api_key.id');

        expect($plainTextKey)->toBeString()->toContain('|');

        $this->assertDatabaseHas('external_api_keys', [
            'id' => $apiKeyId,
            'company_id' => $companyId,
            'name' => 'SEKALORI Landing Page',
            'source_channel' => 'Landing Page',
            'revoked_at' => null,
        ]);

        $this->assertDatabaseMissing('external_api_keys', [
            'id' => $apiKeyId,
            'token_hash' => $plainTextKey,
        ]);

        $this->withToken($ownerToken)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/organization/companies/{$companyId}/api-keys")
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.api_keys')
            ->assertJsonMissing(['plain_text_key' => $plainTextKey]);

        $this->withToken($ownerToken)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/organization/companies/{$companyId}/api-keys/{$apiKeyId}")
            ->assertSuccessful()
            ->assertJsonPath('data.api_key.id', $apiKeyId)
            ->assertJsonMissing(['plain_text_key' => $plainTextKey]);

        $this->withToken($ownerToken)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/organization/companies/{$companyId}/api-keys/{$apiKeyId}/revoke")
            ->assertSuccessful()
            ->assertJsonPath('data.api_key.id', $apiKeyId)
            ->assertJsonPath('data.api_key.revoked', true);

        expect(DB::table('external_api_keys')->where('id', $apiKeyId)->value('revoked_at'))->not->toBeNull();
    });

    it('rotates api keys and rejects old or expired external secrets', function (): void {
        $owner = User::factory()->create();
        [$ownerToken, $companyId] = createOwnedCompanyForApiKeyTest($owner, 'Rotating API Key Company');

        $createResponse = $this->withToken($ownerToken)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/organization/companies/{$companyId}/api-keys", [
                'name' => 'Expiring Landing Page',
                'source_channel' => 'Landing Page',
                'expires_at' => Carbon::now()->addDay()->toISOString(),
            ])
            ->assertCreated();

        $apiKeyId = $createResponse->json('data.api_key.id');
        $oldPlainTextKey = $createResponse->json('data.plain_text_key');

        $this->withHeader('X-API-Key', $oldPlainTextKey)
            ->getJson('/api/v1/external/products')
            ->assertSuccessful();

        $rotateResponse = $this->withToken($ownerToken)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/organization/companies/{$companyId}/api-keys/{$apiKeyId}/rotate")
            ->assertSuccessful()
            ->assertJsonPath('data.api_key.id', $apiKeyId)
            ->assertJsonStructure(['data' => ['plain_text_key']]);

        $newPlainTextKey = $rotateResponse->json('data.plain_text_key');

        expect($newPlainTextKey)->not->toBe($oldPlainTextKey);

        $this->withHeader('X-API-Key', $oldPlainTextKey)
            ->getJson('/api/v1/external/products')
            ->assertUnauthorized();

        $this->withHeader('X-API-Key', $newPlainTextKey)
            ->getJson('/api/v1/external/products')
            ->assertSuccessful();

        DB::table('external_api_keys')
            ->where('id', $apiKeyId)
            ->update(['expires_at' => now()->subMinute()]);

        $this->withHeader('X-API-Key', $newPlainTextKey)
            ->getJson('/api/v1/external/products')
            ->assertUnauthorized();
    });

    it('allows organization.manage-api-keys members and denies members without it', function (): void {
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $member = User::factory()->create();
        [$ownerToken, $companyId] = createOwnedCompanyForApiKeyTest($owner, 'Permission API Key Company');

        Membership::create([
            'company_id' => $companyId,
            'user_id' => $manager->id,
            'role' => 'admin',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        Membership::create([
            'company_id' => $companyId,
            'user_id' => $member->id,
            'role' => 'member',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        setPermissionsTeamId($companyId);
        $permission = Permission::findOrCreate('organization.manage-api-keys', 'api');
        $manager->givePermissionTo($permission);
        setPermissionsTeamId(null);

        $this->withToken(loginForApiKeyTest($manager))
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/organization/companies/{$companyId}/api-keys", [
                'name' => 'Manager Landing Page',
                'source_channel' => 'Landing Page',
            ])
            ->assertCreated();

        $this->withToken(loginForApiKeyTest($member))
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/organization/companies/{$companyId}/api-keys", [
                'name' => 'Member Landing Page',
                'source_channel' => 'Landing Page',
            ])
            ->assertForbidden();

        $this->withToken($ownerToken)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/organization/companies/{$companyId}/api-keys")
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.api_keys');
    });
});
