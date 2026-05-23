<?php

use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\TaxRate;
use App\Modules\Partners\Models\Partner;
use Database\Seeders\CurrencySeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
    $this->seed(CurrencySeeder::class);
});

describe('Finance Invoice Posting and Voiding', function () {
    it('posts a draft invoice to general ledger generating balanced entry', function (): void {
        [, $token, $companyId] = financeActor();

        // 1. Create open period
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/periods', [
                'name' => 'May 2026',
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-31',
            ])->assertCreated();

        // 2. Create accounts
        $arAccount = createAccount($token, $companyId, [
            'code' => '1-1200',
            'name' => 'Accounts Receivable',
            'type' => 'asset',
        ]);
        $revenueAccount = createAccount($token, $companyId, [
            'code' => '4-1100',
            'name' => 'Sales Revenue',
            'type' => 'revenue',
        ]);
        $vatAccount = createAccount($token, $companyId, [
            'code' => '2-1200',
            'name' => 'VAT Output',
            'type' => 'liability',
        ]);

        // 3. Setup mappings
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->putJson('/api/v1/finance/account-mappings', [
                'mappings' => [
                    ['key' => 'accounts_receivable', 'account_id' => $arAccount],
                    ['key' => 'sales_revenue', 'account_id' => $revenueAccount],
                    ['key' => 'vat_output', 'account_id' => $vatAccount],
                ],
            ])->assertSuccessful();

        // 4. Create customer partner
        $partner = Partner::create([
            'company_id' => $companyId,
            'type' => 'customer',
            'name' => 'Acme Corp',
            'code' => 'CUST-001',
            'status' => 'active',
        ]);

        // 5. Create tax rate
        $taxRate = TaxRate::create([
            'company_id' => $companyId,
            'name' => 'VAT 10%',
            'type' => 'vat',
            'rate' => 10.0000,
            'is_active' => true,
        ]);

        // 6. Create invoice draft
        $invoiceId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/invoices', [
                'partner_id' => $partner->id,
                'invoice_date' => '2026-05-15',
                'due_date' => '2026-06-15',
                'lines' => [
                    [
                        'description' => 'Web Dev Services',
                        'quantity' => '1.0000',
                        'unit_price' => '1000000.0000',
                        'tax_rate_id' => $taxRate->id,
                    ],
                ],
            ])
            ->json('data.invoice.id');

        // Post invoice to GL
        $response = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/invoices/{$invoiceId}/post")
            ->assertSuccessful()
            ->assertJsonPath('data.invoice.status', 'posted');

        $invoice = Invoice::findOrFail($invoiceId);
        expect($invoice->journal_entry_id)->not->toBeNull();

        // Check journal lines
        $entryResponse = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/finance/journal-entries/{$invoice->journal_entry_id}")
            ->assertSuccessful();

        // Receivable: 1,100,000 Debit
        // Revenue: 1,000,000 Credit
        // VAT: 100,000 Credit
        $lines = $entryResponse->json('data.journal_entry.lines');
        expect(count($lines))->toBe(3);

        $arLine = collect($lines)->firstWhere('account_id', $arAccount);
        expect((float) $arLine['debit'])->toBe(1100000.0)
            ->and((float) $arLine['credit'])->toBe(0.0);

        $revLine = collect($lines)->firstWhere('account_id', $revenueAccount);
        expect((float) $revLine['debit'])->toBe(0.0)
            ->and((float) $revLine['credit'])->toBe(1000000.0);

        $vatLine = collect($lines)->firstWhere('account_id', $vatAccount);
        expect((float) $vatLine['debit'])->toBe(0.0)
            ->and((float) $vatLine['credit'])->toBe(100000.0);
    });

    it('rejects posting if accounts receivable mapping is missing', function (): void {
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
            'type' => 'customer',
            'name' => 'Acme Corp',
            'code' => 'CUST-001',
            'status' => 'active',
        ]);

        $invoiceId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/invoices', [
                'partner_id' => $partner->id,
                'invoice_date' => '2026-05-15',
                'due_date' => '2026-06-15',
                'lines' => [
                    [
                        'description' => 'Web Dev Services',
                        'quantity' => '1.0000',
                        'unit_price' => '1000000.0000',
                    ],
                ],
            ])
            ->json('data.invoice.id');

        // Post should fail since no AR mapping is registered
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/invoices/{$invoiceId}/post")
            ->assertUnprocessable();
    });

    it('voids a posted invoice by generating reversing general ledger entries', function (): void {
        [, $token, $companyId] = financeActor();

        // 1. Create open period
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/periods', [
                'name' => 'May 2026',
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-31',
            ])->assertCreated();

        $arAccount = createAccount($token, $companyId, ['code' => '1-1200', 'name' => 'AR', 'type' => 'asset']);
        $revenueAccount = createAccount($token, $companyId, ['code' => '4-1100', 'name' => 'Rev', 'type' => 'revenue']);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->putJson('/api/v1/finance/account-mappings', [
                'mappings' => [
                    ['key' => 'accounts_receivable', 'account_id' => $arAccount],
                    ['key' => 'sales_revenue', 'account_id' => $revenueAccount],
                ],
            ])->assertSuccessful();

        $partner = Partner::create([
            'company_id' => $companyId,
            'type' => 'customer',
            'name' => 'Acme Corp',
            'code' => 'CUST-001',
            'status' => 'active',
        ]);

        $invoiceId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/invoices', [
                'partner_id' => $partner->id,
                'invoice_date' => '2026-05-15',
                'due_date' => '2026-06-15',
                'lines' => [
                    [
                        'description' => 'Web Dev Services',
                        'quantity' => '1.0000',
                        'unit_price' => '1000000.0000',
                    ],
                ],
            ])
            ->json('data.invoice.id');

        // Post
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/invoices/{$invoiceId}/post")
            ->assertSuccessful();

        // Void
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/invoices/{$invoiceId}/void")
            ->assertSuccessful()
            ->assertJsonPath('data.invoice.status', 'void');

        $invoice = Invoice::findOrFail($invoiceId);

        // Check that the original journal entry is now void
        $originalEntry = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/finance/journal-entries/{$invoice->journal_entry_id}")
            ->json('data.journal_entry');
        expect($originalEntry['status'])->toBe('void');
    });
});
