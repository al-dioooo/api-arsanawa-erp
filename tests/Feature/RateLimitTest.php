<?php

use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
    // The suite runs with rate limiting disabled (phpunit.xml); opt back in here.
    config()->set('rate-limiting.enabled', true);
});

describe('authenticated API rate limiting', function () {
    it('throttles an authenticated caller past the per-company ceiling with a JSON envelope', function (): void {
        [, $token, $companyId] = financeActor();
        config()->set('rate-limiting.per_company', 2);

        for ($i = 0; $i < 2; $i++) {
            $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
                ->getJson('/api/v1/finance/accounts')
                ->assertSuccessful();
        }

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/finance/accounts')
            ->assertStatus(429)
            ->assertJsonStructure(['message', 'data']);
    });

    it('cannot be bypassed by rotating the caller-controlled X-Company-Id header', function (): void {
        [, $token, $companyId] = financeActor();
        // A low per-user cap with a high per-company cap: only the unforgeable
        // per-user limit can stop a caller who varies the company header.
        config()->set('rate-limiting.per_user', 3);
        config()->set('rate-limiting.per_company', 1000);

        for ($i = 0; $i < 3; $i++) {
            $this->withToken($token)->withHeader('X-Company-Id', (string) ($companyId + $i))
                ->getJson('/api/v1/finance/accounts');
        }

        $this->withToken($token)->withHeader('X-Company-Id', '999999')
            ->getJson('/api/v1/finance/accounts')
            ->assertStatus(429);
    });

    it('does not share a bucket between two different users', function (): void {
        [, $tokenA, $companyA] = financeActor();
        [, $tokenB, $companyB] = financeActor();
        config()->set('rate-limiting.per_company', 1);

        $this->withToken($tokenA)->withHeader('X-Company-Id', (string) $companyA)
            ->getJson('/api/v1/finance/accounts')->assertSuccessful();
        $this->withToken($tokenA)->withHeader('X-Company-Id', (string) $companyA)
            ->getJson('/api/v1/finance/accounts')->assertStatus(429);

        // Both callers are 127.0.0.1 in tests, so this fails loudly if the key
        // ever regresses to an IP.
        $this->withToken($tokenB)->withHeader('X-Company-Id', (string) $companyB)
            ->getJson('/api/v1/finance/accounts')->assertSuccessful();
    });

    it('applies the stricter heavy ceiling to finance exports and still answers JSON', function (): void {
        [, $token, $companyId] = financeActor();
        createAccount($token, $companyId, ['code' => '1-1010', 'name' => 'Cash', 'type' => 'asset']);
        config()->set('rate-limiting.heavy', 1);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->get('/api/v1/finance/exports/income.xlsx')
            ->assertOk();

        // apiDownload() sends Accept: application/octet-stream, which would make
        // Laravel's default throttle response render HTML instead of the envelope.
        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('Accept', 'application/octet-stream')
            ->get('/api/v1/finance/exports/income.xlsx')
            ->assertStatus(429)
            ->assertJsonStructure(['message', 'data']);
    });

    it('never throttles when rate limiting is disabled', function (): void {
        [, $token, $companyId] = financeActor();
        config()->set('rate-limiting.enabled', false);
        config()->set('rate-limiting.per_company', 1);

        for ($i = 0; $i < 5; $i++) {
            $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
                ->getJson('/api/v1/finance/accounts')
                ->assertSuccessful();
        }
    });

    it('keeps the external API on its own ceiling rather than the per-user api throttle', function (): void {
        [, $token, $companyId] = financeActor();

        $key = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/organization/companies/{$companyId}/api-keys", [
                'name' => 'Landing Page',
                'source_channel' => 'landing',
            ])->assertCreated()->json('data.plain_text_key');

        // Clamp the authenticated ceilings only after setup, so the external
        // calls below would trip them if throttle:api were not excluded.
        config()->set('rate-limiting.per_user', 1);
        config()->set('rate-limiting.per_company', 1);

        // Partner integrations must not be capped by a per-user limiter they can
        // never satisfy: throttle:api is excluded from the external group.
        for ($i = 0; $i < 3; $i++) {
            $this->withHeader('X-API-Key', $key)
                ->getJson('/api/v1/external/products')
                ->assertSuccessful();
        }
    });

    it('leaves the health check unthrottled', function (): void {
        config()->set('rate-limiting.guest', 1);

        $this->get('/up')->assertSuccessful();
        $this->get('/up')->assertSuccessful();
    });
});
