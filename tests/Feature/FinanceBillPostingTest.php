<?php

use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Models\TaxRate;
use App\Modules\Partners\Models\Partner;
use Database\Seeders\CurrencySeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
    $this->seed(CurrencySeeder::class);
});

describe('Finance Bill Posting and Voiding', function () {
    it('posts a draft bill to general ledger generating balanced entry', function (): void {
        [, $token, $companyId] = financeActor();

        // 1. Create open period
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/periods', [
                'name' => 'May 2026',
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-31',
            ])->assertCreated();

        // 2. Create accounts
        $apAccount = createAccount($token, $companyId, [
            'code' => '2-1100',
            'name' => 'Accounts Payable',
            'type' => 'liability',
        ]);
        $expenseAccount = createAccount($token, $companyId, [
            'code' => '5-1100',
            'name' => 'Purchase Expense',
            'type' => 'expense',
        ]);
        $vatInputAccount = createAccount($token, $companyId, [
            'code' => '1-1300',
            'name' => 'VAT Input',
            'type' => 'asset',
        ]);
        $whtPayableAccount = createAccount($token, $companyId, [
            'code' => '2-1400',
            'name' => 'Withholding Tax Payable',
            'type' => 'liability',
        ]);

        // 3. Setup mappings
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->putJson('/api/v1/finance/account-mappings', [
                'mappings' => [
                    ['key' => 'accounts_payable', 'account_id' => $apAccount],
                    ['key' => 'purchase_expense', 'account_id' => $expenseAccount],
                    ['key' => 'vat_input', 'account_id' => $vatInputAccount],
                    ['key' => 'withholding_tax_payable', 'account_id' => $whtPayableAccount],
                ],
            ])->assertSuccessful();

        // 4. Create vendor partner
        $partner = Partner::create([
            'company_id' => $companyId,
            'type' => 'vendor',
            'name' => 'Supplier Inc',
            'code' => 'SUP-001',
            'status' => 'active',
        ]);

        // 5. Create tax rates
        $vatRate = TaxRate::create([
            'company_id' => $companyId,
            'name' => 'VAT 10%',
            'type' => 'vat',
            'rate' => 10.0000,
            'is_active' => true,
        ]);

        $whtRate = TaxRate::create([
            'company_id' => $companyId,
            'name' => 'WHT 2%',
            'type' => 'withholding',
            'rate' => 2.0000,
            'is_active' => true,
        ]);

        // 6. Create bill draft
        $billId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/bills', [
                'partner_id' => $partner->id,
                'bill_date' => '2026-05-15',
                'due_date' => '2026-06-15',
                'lines' => [
                    [
                        'description' => 'Consulting Services',
                        'quantity' => '1.0000',
                        'unit_price' => '1000000.0000',
                        'tax_rate_id' => $vatRate->id,
                    ],
                    [
                        'description' => 'Subcontractor Cost',
                        'quantity' => '1.0000',
                        'unit_price' => '500000.0000',
                        'tax_rate_id' => $whtRate->id,
                    ],
                ],
            ])
            ->json('data.bill.id');

        // Post bill to GL
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/bills/{$billId}/post")
            ->assertSuccessful()
            ->assertJsonPath('data.bill.status', 'posted');

        $bill = Bill::findOrFail($billId);
        expect($bill->journal_entry_id)->not->toBeNull();

        // Check journal lines
        $entryResponse = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/finance/journal-entries/{$bill->journal_entry_id}")
            ->assertSuccessful();

        // Line 1: Subtotal = 1,000,000, VAT = 100,000. Line Total = 1,100,000
        // Line 2: Subtotal = 500,000, WHT = 10,000. Line Total = 490,000
        // Bill: Subtotal = 1,500,000, Tax = 100,000, WHT = 10,000, Net Total = 1,590,000
        // Entries expected:
        // - Debit Expense: 1,500,000 (1,000,000 + 500,000)
        // - Debit VAT Input: 100,000
        // - Credit Accounts Payable: 1,590,000
        // - Credit Withholding Tax Payable: 10,000
        $lines = $entryResponse->json('data.journal_entry.lines');
        expect(count($lines))->toBe(5);

        $expLines = collect($lines)->where('account_id', $expenseAccount)->values();
        expect(count($expLines))->toBe(2);
        expect((float) $expLines[0]['debit'])->toBe(1000000.0)
            ->and((float) $expLines[0]['credit'])->toBe(0.0);
        expect((float) $expLines[1]['debit'])->toBe(500000.0)
            ->and((float) $expLines[1]['credit'])->toBe(0.0);

        $vatLine = collect($lines)->firstWhere('account_id', $vatInputAccount);
        expect((float) $vatLine['debit'])->toBe(100000.0)
            ->and((float) $vatLine['credit'])->toBe(0.0);

        $apLine = collect($lines)->firstWhere('account_id', $apAccount);
        expect((float) $apLine['debit'])->toBe(0.0)
            ->and((float) $apLine['credit'])->toBe(1590000.0);

        $whtLine = collect($lines)->firstWhere('account_id', $whtPayableAccount);
        expect((float) $whtLine['debit'])->toBe(0.0)
            ->and((float) $whtLine['credit'])->toBe(10000.0);
    });

    it('rejects posting if accounts payable mapping is missing', function (): void {
        [, $token, $companyId] = financeActor();

        // Create open period
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/periods', [
                'name' => 'May 2026',
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-31',
            ])->assertCreated();

        $partner = Partner::create([
            'company_id' => $companyId,
            'type' => 'vendor',
            'name' => 'Supplier Inc',
            'code' => 'SUP-001',
            'status' => 'active',
        ]);

        $billId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/bills', [
                'partner_id' => $partner->id,
                'bill_date' => '2026-05-15',
                'due_date' => '2026-06-15',
                'lines' => [
                    [
                        'description' => 'Consulting Services',
                        'quantity' => '1.0000',
                        'unit_price' => '1000000.0000',
                    ],
                ],
            ])
            ->json('data.bill.id');

        // Post should fail since no AP mapping is registered
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/bills/{$billId}/post")
            ->assertUnprocessable();
    });

    it('voids a posted bill by generating reversing general ledger entries', function (): void {
        [, $token, $companyId] = financeActor();

        // 1. Create open period
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/periods', [
                'name' => 'May 2026',
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-31',
            ])->assertCreated();

        $apAccount = createAccount($token, $companyId, ['code' => '2-1100', 'name' => 'AP', 'type' => 'liability']);
        $expenseAccount = createAccount($token, $companyId, ['code' => '5-1100', 'name' => 'Exp', 'type' => 'expense']);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->putJson('/api/v1/finance/account-mappings', [
                'mappings' => [
                    ['key' => 'accounts_payable', 'account_id' => $apAccount],
                    ['key' => 'purchase_expense', 'account_id' => $expenseAccount],
                ],
            ])->assertSuccessful();

        $partner = Partner::create([
            'company_id' => $companyId,
            'type' => 'vendor',
            'name' => 'Supplier Inc',
            'code' => 'SUP-001',
            'status' => 'active',
        ]);

        $billId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/bills', [
                'partner_id' => $partner->id,
                'bill_date' => '2026-05-15',
                'due_date' => '2026-06-15',
                'lines' => [
                    [
                        'description' => 'Consulting Services',
                        'quantity' => '1.0000',
                        'unit_price' => '1000000.0000',
                    ],
                ],
            ])
            ->json('data.bill.id');

        // Post
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/bills/{$billId}/post")
            ->assertSuccessful();

        // Void
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/bills/{$billId}/void")
            ->assertSuccessful()
            ->assertJsonPath('data.bill.status', 'void');

        $bill = Bill::findOrFail($billId);

        // Check that the original journal entry is now void
        $originalEntry = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/finance/journal-entries/{$bill->journal_entry_id}")
            ->json('data.journal_entry');
        expect($originalEntry['status'])->toBe('void');
    });
});
