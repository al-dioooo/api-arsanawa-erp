<?php

use App\Models\User;
use App\Modules\Organization\Models\Membership;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
    config([
        'services.whatsapp.enabled' => true,
        'services.whatsapp.driver' => 'log',
    ]);
});

describe('Platform WhatsApp test endpoint', function () {
    it('dispatches a test message and reports sent', function (): void {
        [, $token, $companyId] = financeActor();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/platform/whatsapp/test', ['to' => '08123456789'])
            ->assertOk()
            ->assertJsonPath('data.status', 'sent');
    });

    it('rejects a phone number with no digits', function (): void {
        [, $token, $companyId] = financeActor();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/platform/whatsapp/test', ['to' => 'abc'])
            ->assertStatus(422);
    });

    it('requires authentication', function (): void {
        $this->postJson('/api/v1/platform/whatsapp/test', ['to' => '08123456789'])
            ->assertUnauthorized();
    });

    it('forbids sending for a member without platform.manage-settings', function (): void {
        [, , $companyId] = financeActor();

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
            ->postJson('/api/v1/platform/whatsapp/test', ['to' => '08123456789'])
            ->assertForbidden();
    });
});
