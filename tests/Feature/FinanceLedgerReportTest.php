<?php

use App\Models\User;
use App\Modules\Finance\Models\Account;
use Database\Seeders\CurrencySeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
    $this->seed(CurrencySeeder::class);
});

describe('Finance ledger reports', function () {
    it('generates a trial balance report excluding drafts', function (): void {
        [$owner, $token, $companyId] = financeActor();

        // Create open period
        $periodId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/periods', [
                'name' => 'January 2026',
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
            ])->json('data.period.id');

        $cashId = createAccount($token, $companyId, [
            'code' => '1-1100',
            'name' => 'Cash',
            'type' => 'asset',
        ]);

        $capitalId = createAccount($token, $companyId, [
            'code' => '3-1100',
            'name' => 'Owner Capital',
            'type' => 'equity',
        ]);

        // 1. Post a balanced journal entry
        $entry1Response = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/journal-entries', [
                'entry_date' => '2026-01-15',
                'description' => 'Owner capital contribution',
                'currency_id' => 1,
                'exchange_rate' => 1.0,
                'lines' => [
                    [
                        'account_id' => $cashId,
                        'debit' => 10000000.0,
                        'credit' => 0,
                    ],
                    [
                        'account_id' => $capitalId,
                        'debit' => 0,
                        'credit' => 10000000.0,
                    ],
                ],
            ]);

        $entry1Id = $entry1Response->json('data.journal_entry.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/journal-entries/{$entry1Id}/post")
            ->assertSuccessful();

        // 2. Create a DRAFT entry (should not be in trial balance)
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/journal-entries', [
                'entry_date' => '2026-01-20',
                'description' => 'Draft purchase',
                'currency_id' => 1,
                'exchange_rate' => 1.0,
                'lines' => [
                    [
                        'account_id' => $cashId,
                        'debit' => 0,
                        'credit' => 100000.0,
                    ],
                    [
                        'account_id' => $capitalId,
                        'debit' => 100000.0,
                        'credit' => 0,
                    ],
                ],
            ])->assertCreated();

        // Get trial balance
        $response = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/finance/reports/trial-balance?accounting_period_id={$periodId}")
            ->assertSuccessful();

        $rows = $response->json('data.trial_balance');
        expect($rows)->toHaveCount(2);

        // Find cash row
        $cashRow = collect($rows)->firstWhere('account_id', $cashId);
        expect($cashRow)->not->toBeNull();
        expect((float) $cashRow['debit'])->toEqual(10000000.0);
        expect((float) $cashRow['credit'])->toEqual(0.0);

        // Find capital row
        $capitalRow = collect($rows)->firstWhere('account_id', $capitalId);
        expect($capitalRow)->not->toBeNull();
        expect((float) $capitalRow['debit'])->toEqual(0.0);
        expect((float) $capitalRow['credit'])->toEqual(10000000.0);
    });

    it('generates an account ledger report showing running balance', function (): void {
        [$owner, $token, $companyId] = financeActor();

        // Create open period
        $periodId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/periods', [
                'name' => 'January 2026',
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
            ])->json('data.period.id');

        $cashId = createAccount($token, $companyId, [
            'code' => '1-1100',
            'name' => 'Cash',
            'type' => 'asset',
        ]);

        $capitalId = createAccount($token, $companyId, [
            'code' => '3-1100',
            'name' => 'Owner Capital',
            'type' => 'equity',
        ]);

        // Post entry 1 (Debit Cash 10M, Credit Capital 10M)
        $entry1Id = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/journal-entries', [
                'entry_date' => '2026-01-15',
                'description' => 'Capital contribution',
                'currency_id' => 1,
                'exchange_rate' => 1.0,
                'lines' => [
                    [
                        'account_id' => $cashId,
                        'debit' => 10000000.0,
                        'credit' => 0,
                    ],
                    [
                        'account_id' => $capitalId,
                        'debit' => 0,
                        'credit' => 10000000.0,
                    ],
                ],
            ])->json('data.journal_entry.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/journal-entries/{$entry1Id}/post")
            ->assertSuccessful();

        // Post entry 2 (Debit Capital 2M, Credit Cash 2M)
        $entry2Id = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/journal-entries', [
                'entry_date' => '2026-01-20',
                'description' => 'Capital withdrawal',
                'currency_id' => 1,
                'exchange_rate' => 1.0,
                'lines' => [
                    [
                        'account_id' => $cashId,
                        'debit' => 0,
                        'credit' => 2000000.0,
                    ],
                    [
                        'account_id' => $capitalId,
                        'debit' => 2000000.0,
                        'credit' => 0,
                    ],
                ],
            ])->json('data.journal_entry.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/journal-entries/{$entry2Id}/post")
            ->assertSuccessful();

        // Get account ledger for Cash (Asset - normal balance: debit)
        $response = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/finance/reports/account-ledger?account_id={$cashId}&accounting_period_id={$periodId}")
            ->assertSuccessful();

        $ledger = $response->json('data.ledger');
        expect($ledger)->toHaveCount(2);

        // Verification of Cash ledger rows:
        // Row 1: Debit 10M, Credit 0, Running Balance 10M
        expect((float) $ledger[0]['debit'])->toEqual(10000000.0);
        expect((float) $ledger[0]['credit'])->toEqual(0.0);
        expect((float) $ledger[0]['balance'])->toEqual(10000000.0);

        // Row 2: Debit 0, Credit 2M, Running Balance 8M
        expect((float) $ledger[1]['debit'])->toEqual(0.0);
        expect((float) $ledger[1]['credit'])->toEqual(2000000.0);
        expect((float) $ledger[1]['balance'])->toEqual(8000000.0);
    });

    it('rejects reports access without permission', function (): void {
        $user = User::factory()->create();
        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.access_token');

        $this->withToken($token)
            ->getJson('/api/v1/finance/reports/trial-balance?accounting_period_id=1')
            ->assertForbidden();
    });
});
