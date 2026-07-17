<?php

use App\Modules\Platform\Services\WhatsApp\WhatsAppService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
});

function upsertWhatsAppToken(string $token, string $tokenValue): void
{
    test()->withToken($token)
        ->putJson('/api/v1/platform/settings', [
            'settings' => [
                ['module' => 'whatsapp', 'key' => 'token', 'value' => $tokenValue],
            ],
        ])->assertSuccessful();
}

describe('secret platform settings', function () {
    it('never echoes a stored credential back on read', function (): void {
        [, $token, $companyId] = financeActor();
        upsertWhatsAppToken($token, 'super-secret-token');

        $response = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/platform/settings?module=whatsapp')
            ->assertSuccessful()
            ->assertJsonPath('data.settings.0.value', null)
            ->assertJsonPath('data.settings.0.is_secret', true)
            ->assertJsonPath('data.settings.0.is_set', true);

        // Catches a leak through any other field, not just `value`.
        $response->assertDontSee('super-secret-token');
    });

    it('does not echo the credential back on the write response either', function (): void {
        [, $token] = financeActor();

        $this->withToken($token)
            ->putJson('/api/v1/platform/settings', [
                'settings' => [
                    ['module' => 'whatsapp', 'key' => 'token', 'value' => 'super-secret-token'],
                ],
            ])
            ->assertSuccessful()
            ->assertDontSee('super-secret-token');
    });

    it('treats a redacted null round-trip as a no-op rather than wiping the credential', function (): void {
        [, $token, $companyId] = financeActor();
        upsertWhatsAppToken($token, 'super-secret-token');

        // Exactly what a client does when it reads all settings and saves them back.
        $this->withToken($token)
            ->putJson('/api/v1/platform/settings', [
                'settings' => [
                    ['module' => 'whatsapp', 'key' => 'token', 'value' => null],
                ],
            ])->assertSuccessful();

        expect(app(WhatsAppService::class)->credentialsFor($companyId)['token'])
            ->toBe('super-secret-token');
    });

    it('clears the credential on an explicit empty string', function (): void {
        [, $token, $companyId] = financeActor();
        upsertWhatsAppToken($token, 'super-secret-token');

        upsertWhatsAppToken($token, '');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/platform/settings?module=whatsapp')
            ->assertJsonPath('data.settings.0.is_set', false);
    });

    it('leaves non-secret settings readable', function (): void {
        [, $token, $companyId] = financeActor();

        $this->withToken($token)
            ->putJson('/api/v1/platform/settings', [
                'settings' => [
                    ['module' => 'whatsapp', 'key' => 'sender', 'value' => '628123456789'],
                ],
            ])->assertSuccessful();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/platform/settings?module=whatsapp')
            ->assertSuccessful()
            ->assertJsonPath('data.settings.0.is_secret', false)
            ->assertJsonPath('data.settings.0.value', '628123456789');
    });

    it('stores the credential encrypted at rest', function (): void {
        [, $token] = financeActor();
        upsertWhatsAppToken($token, 'super-secret-token');

        $stored = DB::table('settings')->where('module', 'whatsapp')->where('key', 'token')->value('value');

        expect($stored)->not->toContain('super-secret-token')
            ->and($stored)->toStartWith('"enc:v1:');
    });

    it('still reads credentials written before encryption existed', function (): void {
        [, $token, $companyId] = financeActor();
        // Establish the row through the normal path, then overwrite it with a
        // bare legacy value exactly as an older release would have stored it.
        upsertWhatsAppToken($token, 'placeholder');
        DB::table('settings')->where('module', 'whatsapp')->where('key', 'token')
            ->update(['value' => json_encode('legacy-token')]);

        expect(app(WhatsAppService::class)->credentialsFor($companyId)['token'])->toBe('legacy-token');
    });

    it('sends using the decrypted credential', function (): void {
        [, $token, $companyId] = financeActor();
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.driver' => 'fonnte',
        ]);
        Http::fake();

        upsertWhatsAppToken($token, 'super-secret-token');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/platform/whatsapp/test', ['to' => '08123456789'])
            ->assertOk();

        // The regression guard for encryption: the wire must carry plaintext.
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'super-secret-token'));
    });

    it('backfills legacy plaintext exactly once, and can be rolled back', function (): void {
        [, $token, $companyId] = financeActor();
        upsertWhatsAppToken($token, 'placeholder');
        DB::table('settings')->where('module', 'whatsapp')->where('key', 'token')
            ->update(['value' => json_encode('legacy-token')]);

        $migration = require base_path('app/Modules/Platform/database/migrations/2026_07_16_000000_encrypt_secret_settings.php');

        $migration->up();
        $afterFirst = DB::table('settings')->where('module', 'whatsapp')->where('key', 'token')->value('value');

        // Running it again must not encrypt the ciphertext a second time.
        $migration->up();
        $afterSecond = DB::table('settings')->where('module', 'whatsapp')->where('key', 'token')->value('value');

        expect($afterFirst)->toStartWith('"enc:v1:')
            ->and($afterSecond)->toBe($afterFirst)
            ->and(app(WhatsAppService::class)->credentialsFor($companyId)['token'])->toBe('legacy-token');

        $migration->down();
        expect(DB::table('settings')->where('module', 'whatsapp')->where('key', 'token')->value('value'))
            ->toBe(json_encode('legacy-token'));
    });

    it('still serves settings for a module a client wrote an encryption-marker string into', function (): void {
        [, $token, $companyId] = financeActor();

        // The upsert endpoint accepts free-form module/key/value, so a client can
        // write anything anywhere. Only registry keys are ever treated as secrets,
        // so this must stay inert rather than poison reads of the module.
        $this->withToken($token)
            ->putJson('/api/v1/platform/settings', [
                'settings' => [
                    ['module' => 'inventory', 'key' => 'discounts_enabled', 'value' => 'enc:v1:garbage'],
                ],
            ])->assertSuccessful();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/platform/settings?module=inventory')
            ->assertSuccessful()
            ->assertJsonPath('data.settings.0.is_secret', false);
    });
});
