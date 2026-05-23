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

describe('Finance manual journal entries', function () {
    it('creates a draft journal entry and lists it', function (): void {
        [$owner, $token, $companyId] = financeActor();

        // Create open period
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/periods', [
                'name' => 'January 2026',
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
            ])->assertCreated();

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

        // Create draft
        $response = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/journal-entries', [
                'entry_date' => '2026-01-15',
                'description' => 'Owner capital contribution',
                'currency_id' => 1, // IDR
                'exchange_rate' => 1.0,
                'lines' => [
                    [
                        'account_id' => $cashId,
                        'description' => 'Dr Cash',
                        'debit' => 10000000.0,
                        'credit' => 0,
                    ],
                    [
                        'account_id' => $capitalId,
                        'description' => 'Cr Capital',
                        'debit' => 0,
                        'credit' => 10000000.0,
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.journal_entry.status', 'draft');

        $entryId = $response->json('data.journal_entry.id');

        // List
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/finance/journal-entries')
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.journal_entries');

        // Show
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/finance/journal-entries/{$entryId}")
            ->assertSuccessful()
            ->assertJsonPath('data.journal_entry.description', 'Owner capital contribution');
    });

    it('rejects posting an unbalanced journal entry', function (): void {
        [$owner, $token, $companyId] = financeActor();

        // Create open period
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/periods', [
                'name' => 'January 2026',
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
            ])->assertCreated();

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

        // Create unbalanced draft
        $entryId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/journal-entries', [
                'entry_date' => '2026-01-15',
                'description' => 'Unbalanced contribution',
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
                        'credit' => 9000000.0, // Difference of 1,000,000
                    ],
                ],
            ])
            ->json('data.journal_entry.id');

        // Post should fail
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/journal-entries/{$entryId}/post")
            ->assertUnprocessable();
    });

    it('posts a balanced journal entry', function (): void {
        [$owner, $token, $companyId] = financeActor();

        // Create open period
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/periods', [
                'name' => 'January 2026',
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
            ])->assertCreated();

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

        // Create balanced draft
        $entryId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
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
            ])
            ->json('data.journal_entry.id');

        // Post should succeed
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/journal-entries/{$entryId}/post")
            ->assertSuccessful()
            ->assertJsonPath('data.journal_entry.status', 'posted');
    });

    it('rejects posting to a closed period', function (): void {
        [$owner, $token, $companyId] = financeActor();

        // Create period
        $periodId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/periods', [
                'name' => 'January 2026',
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
            ])->json('data.period.id');

        // Close period
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/periods/{$periodId}/close")->assertSuccessful();

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

        // Create balanced draft
        $entryId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
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
            ])
            ->json('data.journal_entry.id');

        // Post should fail
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/journal-entries/{$entryId}/post")
            ->assertUnprocessable();
    });

    it('rejects posting to a non-postable (parent) account', function (): void {
        [$owner, $token, $companyId] = financeActor();

        // Create open period
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/periods', [
                'name' => 'January 2026',
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
            ])->assertCreated();

        $assetsId = createAccount($token, $companyId, [
            'code' => '1-0000',
            'name' => 'Assets',
            'type' => 'asset',
        ]);

        // Cash child makes Assets a parent (non-postable)
        createAccount($token, $companyId, [
            'code' => '1-1100',
            'name' => 'Cash',
            'type' => 'asset',
            'parent_id' => $assetsId,
        ]);

        $capitalId = createAccount($token, $companyId, [
            'code' => '3-1100',
            'name' => 'Owner Capital',
            'type' => 'equity',
        ]);

        // Create draft referencing parent account
        $entryId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/journal-entries', [
                'entry_date' => '2026-01-15',
                'description' => 'Referencing parent account',
                'currency_id' => 1,
                'exchange_rate' => 1.0,
                'lines' => [
                    [
                        'account_id' => $assetsId, // non-postable parent
                        'debit' => 10000000.0,
                        'credit' => 0,
                    ],
                    [
                        'account_id' => $capitalId,
                        'debit' => 0,
                        'credit' => 10000000.0,
                    ],
                ],
            ])
            ->json('data.journal_entry.id');

        // Post should fail
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/journal-entries/{$entryId}/post")
            ->assertUnprocessable();
    });

    it('voiding a posted journal entry creates a reversing entry', function (): void {
        [$owner, $token, $companyId] = financeActor();

        // Create open period
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/periods', [
                'name' => 'January 2026',
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
            ])->assertCreated();

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

        // Create and post balanced entry
        $entryId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
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
            ])
            ->json('data.journal_entry.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/journal-entries/{$entryId}/post")
            ->assertSuccessful();

        // Void the entry
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/journal-entries/{$entryId}/void")
            ->assertSuccessful()
            ->assertJsonPath('data.journal_entry.status', 'void');

        // Check reversed/void entry status in database
        $this->assertDatabaseHas('journal_entries', [
            'id' => $entryId,
            'status' => 'void',
        ]);

        // Check that a reversing entry is created
        $this->assertDatabaseHas('journal_entries', [
            'company_id' => $companyId,
            'description' => 'Reversal of entry #'.$entryId.': Owner capital contribution',
            'status' => 'posted',
        ]);
    });

    it('rejects journal entry operations without permission', function (): void {
        $user = User::factory()->create();
        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.access_token');

        $this->withToken($token)
            ->postJson('/api/v1/finance/journal-entries', [
                'entry_date' => '2026-01-15',
                'description' => 'No permission',
                'currency_id' => 1,
                'exchange_rate' => 1.0,
                'lines' => [],
            ])
            ->assertForbidden();
    });
});
