<?php

use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
});

describe('Finance tax rates and account mappings', function () {
    it('creates and lists tax rates', function (): void {
        [, $token, $companyId] = financeActor();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/tax-rates', [
                'name' => 'PPN 11%',
                'type' => 'vat',
                'rate' => 11,
            ])
            ->assertCreated()
            ->assertJsonPath('data.tax_rate.name', 'PPN 11%')
            ->assertJsonPath('data.tax_rate.type', 'vat');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/finance/tax-rates')
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.tax_rates');
    });

    it('updates and deletes a tax rate', function (): void {
        [, $token, $companyId] = financeActor();

        $taxRateId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/tax-rates', [
                'name' => 'PPh 2%',
                'type' => 'withholding',
                'rate' => 2,
            ])
            ->json('data.tax_rate.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->patchJson("/api/v1/finance/tax-rates/{$taxRateId}", ['name' => 'PPh 2.5%', 'rate' => 2.5])
            ->assertSuccessful()
            ->assertJsonPath('data.tax_rate.name', 'PPh 2.5%');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->deleteJson("/api/v1/finance/tax-rates/{$taxRateId}")
            ->assertSuccessful();
    });

    it('upserts account mappings and lists them', function (): void {
        [, $token, $companyId] = financeActor();

        $arAccountId = createAccount($token, $companyId, [
            'code' => '1-1100',
            'name' => 'Accounts Receivable',
            'type' => 'asset',
        ]);

        $revenueAccountId = createAccount($token, $companyId, [
            'code' => '4-1000',
            'name' => 'Sales Revenue',
            'type' => 'revenue',
        ]);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->putJson('/api/v1/finance/account-mappings', [
                'mappings' => [
                    ['key' => 'accounts_receivable', 'account_id' => $arAccountId],
                    ['key' => 'sales_revenue', 'account_id' => $revenueAccountId],
                ],
            ])
            ->assertSuccessful()
            ->assertJsonCount(2, 'data.account_mappings');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/finance/account-mappings')
            ->assertSuccessful()
            ->assertJsonCount(2, 'data.account_mappings');
    });

    it('rejects mapping to an account from another company', function (): void {
        [, $tokenA, $companyA] = financeActor();
        [, $tokenB, $companyB] = financeActor();

        $foreignAccountId = createAccount($tokenA, $companyA, [
            'code' => '1-1100',
            'name' => 'Foreign AR',
            'type' => 'asset',
        ]);

        $this->withToken($tokenB)->withHeader('X-Company-Id', (string) $companyB)
            ->putJson('/api/v1/finance/account-mappings', [
                'mappings' => [
                    ['key' => 'accounts_receivable', 'account_id' => $foreignAccountId],
                ],
            ])
            ->assertUnprocessable();
    });

    it('rejects tax rate creation without permission', function (): void {
        $user = User::factory()->create();
        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.access_token');

        $this->withToken($token)
            ->postJson('/api/v1/finance/tax-rates', [
                'name' => 'Test Tax',
                'type' => 'vat',
                'rate' => 10,
            ])
            ->assertForbidden();
    });
});
