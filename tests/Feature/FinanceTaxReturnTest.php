<?php

use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\TaxRate;
use App\Modules\Finance\Models\TaxReturn;
use App\Modules\Partners\Models\Partner;
use Database\Seeders\CurrencySeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
    $this->seed(CurrencySeeder::class);
});

describe('Finance Periodic Tax Returns', function () {
    it('creates a draft VAT (PPN) tax return and lists it', function (): void {
        [, $token, $companyId, $branchId] = financeActor();

        // 1. Create open period
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/periods', [
                'name' => 'May 2026',
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-31',
            ])->assertCreated();

        // 2. Setup tax rate
        $taxRate = TaxRate::create([
            'company_id' => $companyId,
            'name' => 'VAT 11%',
            'type' => 'vat',
            'rate' => 11.0000,
            'is_active' => true,
        ]);

        // 3. Create posted Invoice with VAT
        $partner = Partner::create([
            'company_id' => $companyId,
            'type' => 'customer',
            'name' => 'Acme Corp',
            'code' => 'CUST-001',
            'status' => 'active',
        ]);

        Invoice::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'invoice_number' => 'INV-001',
            'partner_id' => $partner->id,
            'currency_id' => 1,
            'exchange_rate' => 1.0,
            'invoice_date' => '2026-05-10',
            'due_date' => '2026-06-10',
            'status' => 'posted',
            'subtotal' => 1000000.0,
            'tax_total' => 110000.0,
            'total' => 1110000.0,
        ]);

        // 4. Create posted Bill with VAT
        $vendor = Partner::create([
            'company_id' => $companyId,
            'type' => 'vendor',
            'name' => 'Vendor Corp',
            'code' => 'VEND-001',
            'status' => 'active',
        ]);

        Bill::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'bill_number' => 'BILL-001',
            'partner_id' => $vendor->id,
            'currency_id' => 1,
            'exchange_rate' => 1.0,
            'bill_date' => '2026-05-15',
            'due_date' => '2026-06-15',
            'status' => 'posted',
            'subtotal' => 500000.0,
            'tax_total' => 55000.0,
            'total' => 555000.0,
        ]);

        // 5. Generate Tax Return
        $response = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/tax-returns', [
                'tax_type' => 'vat',
                'period_start' => '2026-05-01',
                'period_end' => '2026-05-31',
            ])
            ->assertCreated()
            ->assertJsonPath('data.tax_return.tax_type', 'vat')
            ->assertJsonPath('data.tax_return.total_output', '110000.0000')
            ->assertJsonPath('data.tax_return.total_input', '55000.0000')
            ->assertJsonPath('data.tax_return.total_payable', '55000.0000')
            ->assertJsonPath('data.tax_return.status', 'draft');

        $taxReturnId = $response->json('data.tax_return.id');

        // Check if details are returned and lists correctly
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/finance/tax-returns/{$taxReturnId}")
            ->assertSuccessful()
            ->assertJsonCount(2, 'data.tax_return.lines');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/finance/tax-returns')
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.tax_returns');
    });

    it('creates a draft Withholding (PPh) tax return', function (): void {
        [, $token, $companyId, $branchId] = financeActor();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/periods', [
                'name' => 'May 2026',
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-31',
            ])->assertCreated();

        $vendor = Partner::create([
            'company_id' => $companyId,
            'type' => 'vendor',
            'name' => 'Vendor Corp',
            'code' => 'VEND-001',
            'status' => 'active',
        ]);

        // Create posted Bill with withholding
        Bill::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'bill_number' => 'BILL-001',
            'partner_id' => $vendor->id,
            'currency_id' => 1,
            'exchange_rate' => 1.0,
            'bill_date' => '2026-05-15',
            'due_date' => '2026-06-15',
            'status' => 'posted',
            'subtotal' => 1000000.0,
            'tax_total' => 0.0,
            'withholding_total' => 20000.0,
            'total' => 980000.0,
        ]);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/tax-returns', [
                'tax_type' => 'withholding',
                'period_start' => '2026-05-01',
                'period_end' => '2026-05-31',
            ])
            ->assertCreated()
            ->assertJsonPath('data.tax_return.tax_type', 'withholding')
            ->assertJsonPath('data.tax_return.total_output', '0.0000')
            ->assertJsonPath('data.tax_return.total_input', '0.0000')
            ->assertJsonPath('data.tax_return.total_payable', '20000.0000');
    });

    it('rejects overlapping tax returns for the same type and period range', function (): void {
        [, $token, $companyId] = financeActor();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/periods', [
                'name' => 'May 2026',
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-31',
            ])->assertCreated();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/tax-returns', [
                'tax_type' => 'vat',
                'period_start' => '2026-05-01',
                'period_end' => '2026-05-31',
            ])->assertCreated();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/tax-returns', [
                'tax_type' => 'vat',
                'period_start' => '2026-05-01',
                'period_end' => '2026-05-31',
            ])->assertUnprocessable();
    });

    it('excludes draft, void, outside range, or already filed invoices/bills', function (): void {
        [, $token, $companyId, $branchId] = financeActor();

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

        // Draft invoice
        Invoice::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'invoice_number' => 'INV-DRAFT',
            'partner_id' => $partner->id,
            'currency_id' => 1,
            'exchange_rate' => 1.0,
            'invoice_date' => '2026-05-10',
            'due_date' => '2026-06-10',
            'status' => 'draft',
            'subtotal' => 1000000.0,
            'tax_total' => 110000.0,
            'total' => 1110000.0,
        ]);

        // Void invoice
        Invoice::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'invoice_number' => 'INV-VOID',
            'partner_id' => $partner->id,
            'currency_id' => 1,
            'exchange_rate' => 1.0,
            'invoice_date' => '2026-05-10',
            'due_date' => '2026-06-10',
            'status' => 'void',
            'subtotal' => 1000000.0,
            'tax_total' => 110000.0,
            'total' => 1110000.0,
        ]);

        // Outside range invoice
        Invoice::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'invoice_number' => 'INV-OUT',
            'partner_id' => $partner->id,
            'currency_id' => 1,
            'exchange_rate' => 1.0,
            'invoice_date' => '2026-06-01',
            'due_date' => '2026-07-01',
            'status' => 'posted',
            'subtotal' => 1000000.0,
            'tax_total' => 110000.0,
            'total' => 1110000.0,
        ]);

        // Valid Invoice
        Invoice::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'invoice_number' => 'INV-VALID',
            'partner_id' => $partner->id,
            'currency_id' => 1,
            'exchange_rate' => 1.0,
            'invoice_date' => '2026-05-10',
            'due_date' => '2026-06-10',
            'status' => 'posted',
            'subtotal' => 1000000.0,
            'tax_total' => 110000.0,
            'total' => 1110000.0,
        ]);

        // Generate return 1
        $res1 = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/tax-returns', [
                'tax_type' => 'vat',
                'period_start' => '2026-05-01',
                'period_end' => '2026-05-31',
            ])
            ->assertCreated();

        $taxReturnId1 = $res1->json('data.tax_return.id');

        // Let's create another period for June
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/periods', [
                'name' => 'June 2026',
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-30',
            ])->assertCreated();

        // Finalize return 1
        // Create accounts & mappings first
        $vatOutputAcc = createAccount($token, $companyId, ['code' => '2-1200', 'name' => 'VAT Output', 'type' => 'liability']);
        $vatInputAcc = createAccount($token, $companyId, ['code' => '1-1300', 'name' => 'VAT Input', 'type' => 'asset']);
        $taxPayableAcc = createAccount($token, $companyId, ['code' => '2-1300', 'name' => 'Tax Payable', 'type' => 'liability']);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->putJson('/api/v1/finance/account-mappings', [
                'mappings' => [
                    ['key' => 'vat_output', 'account_id' => $vatOutputAcc],
                    ['key' => 'vat_input', 'account_id' => $vatInputAcc],
                    ['key' => 'tax_payable', 'account_id' => $taxPayableAcc],
                ],
            ])->assertSuccessful();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/tax-returns/{$taxReturnId1}/finalize")
            ->assertSuccessful();

        // Try to generate another tax return covering June - it should aggregate only INV-OUT
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/tax-returns', [
                'tax_type' => 'vat',
                'period_start' => '2026-06-01',
                'period_end' => '2026-06-30',
            ])
            ->assertCreated()
            ->assertJsonPath('data.tax_return.total_output', '110000.0000');
    });

    it('finalizes VAT tax return and creates general ledger entry', function (): void {
        [, $token, $companyId, $branchId] = financeActor();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/periods', [
                'name' => 'May 2026',
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-31',
            ])->assertCreated();

        $vatOutputAcc = createAccount($token, $companyId, ['code' => '2-1200', 'name' => 'VAT Output', 'type' => 'liability']);
        $vatInputAcc = createAccount($token, $companyId, ['code' => '1-1300', 'name' => 'VAT Input', 'type' => 'asset']);
        $taxPayableAcc = createAccount($token, $companyId, ['code' => '2-1300', 'name' => 'Tax Payable', 'type' => 'liability']);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->putJson('/api/v1/finance/account-mappings', [
                'mappings' => [
                    ['key' => 'vat_output', 'account_id' => $vatOutputAcc],
                    ['key' => 'vat_input', 'account_id' => $vatInputAcc],
                    ['key' => 'tax_payable', 'account_id' => $taxPayableAcc],
                ],
            ])->assertSuccessful();

        $partner = Partner::create([
            'company_id' => $companyId,
            'type' => 'customer',
            'name' => 'Acme Corp',
            'code' => 'CUST-001',
            'status' => 'active',
        ]);

        $vendor = Partner::create([
            'company_id' => $companyId,
            'type' => 'vendor',
            'name' => 'Vendor Corp',
            'code' => 'VEND-001',
            'status' => 'active',
        ]);

        // Output: 100,000
        Invoice::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'invoice_number' => 'INV-1',
            'partner_id' => $partner->id,
            'currency_id' => 1,
            'exchange_rate' => 1.0,
            'invoice_date' => '2026-05-10',
            'due_date' => '2026-06-10',
            'status' => 'posted',
            'subtotal' => 1000000.0,
            'tax_total' => 100000.0,
            'total' => 1100000.0,
        ]);

        // Input: 40,000
        Bill::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'bill_number' => 'BILL-1',
            'partner_id' => $vendor->id,
            'currency_id' => 1,
            'exchange_rate' => 1.0,
            'bill_date' => '2026-05-15',
            'due_date' => '2026-06-15',
            'status' => 'posted',
            'subtotal' => 400000.0,
            'tax_total' => 40000.0,
            'total' => 440000.0,
        ]);

        $response = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/tax-returns', [
                'tax_type' => 'vat',
                'period_start' => '2026-05-01',
                'period_end' => '2026-05-31',
            ])
            ->assertCreated();

        $taxReturnId = $response->json('data.tax_return.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/tax-returns/{$taxReturnId}/finalize")
            ->assertSuccessful()
            ->assertJsonPath('data.tax_return.status', 'finalized');

        // Verify journal entry
        $taxReturn = TaxReturn::findOrFail($taxReturnId);
        expect($taxReturn->journal_entry_id)->not->toBeNull();

        $entryResponse = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/finance/journal-entries/{$taxReturn->journal_entry_id}")
            ->assertSuccessful();

        $lines = $entryResponse->json('data.journal_entry.lines');
        expect($lines)->toHaveCount(3);

        $vatOutputLine = collect($lines)->firstWhere('account_id', $vatOutputAcc);
        $vatInputLine = collect($lines)->firstWhere('account_id', $vatInputAcc);
        $taxPayableLine = collect($lines)->firstWhere('account_id', $taxPayableAcc);

        expect($vatOutputLine['debit'])->toEqual('100000.0000');
        expect($vatInputLine['credit'])->toEqual('40000.0000');
        expect($taxPayableLine['credit'])->toEqual('60000.0000');
    });

    it('finalizes withholding tax return and creates general ledger entry', function (): void {
        [, $token, $companyId, $branchId] = financeActor();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/periods', [
                'name' => 'May 2026',
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-31',
            ])->assertCreated();

        $withholdingTaxPayableAcc = createAccount($token, $companyId, ['code' => '2-1400', 'name' => 'Withholding Payable', 'type' => 'liability']);
        $taxPayableAcc = createAccount($token, $companyId, ['code' => '2-1300', 'name' => 'Tax Payable', 'type' => 'liability']);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->putJson('/api/v1/finance/account-mappings', [
                'mappings' => [
                    ['key' => 'withholding_tax_payable', 'account_id' => $withholdingTaxPayableAcc],
                    ['key' => 'tax_payable', 'account_id' => $taxPayableAcc],
                ],
            ])->assertSuccessful();

        $vendor = Partner::create([
            'company_id' => $companyId,
            'type' => 'vendor',
            'name' => 'Vendor Corp',
            'code' => 'VEND-001',
            'status' => 'active',
        ]);

        Bill::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'bill_number' => 'BILL-1',
            'partner_id' => $vendor->id,
            'currency_id' => 1,
            'exchange_rate' => 1.0,
            'bill_date' => '2026-05-15',
            'due_date' => '2026-06-15',
            'status' => 'posted',
            'subtotal' => 1000000.0,
            'tax_total' => 0.0,
            'withholding_total' => 20000.0,
            'total' => 980000.0,
        ]);

        $response = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/tax-returns', [
                'tax_type' => 'withholding',
                'period_start' => '2026-05-01',
                'period_end' => '2026-05-31',
            ])
            ->assertCreated();

        $taxReturnId = $response->json('data.tax_return.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/tax-returns/{$taxReturnId}/finalize")
            ->assertSuccessful();

        $taxReturn = TaxReturn::findOrFail($taxReturnId);
        expect($taxReturn->journal_entry_id)->not->toBeNull();

        $entryResponse = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/finance/journal-entries/{$taxReturn->journal_entry_id}")
            ->assertSuccessful();

        $lines = $entryResponse->json('data.journal_entry.lines');
        expect($lines)->toHaveCount(2);

        $withholdingLine = collect($lines)->firstWhere('account_id', $withholdingTaxPayableAcc);
        $taxPayableLine = collect($lines)->firstWhere('account_id', $taxPayableAcc);

        expect($withholdingLine['debit'])->toEqual('20000.0000');
        expect($taxPayableLine['credit'])->toEqual('20000.0000');
    });

    it('finalizes a nil return without generating a journal entry', function (): void {
        [, $token, $companyId] = financeActor();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/periods', [
                'name' => 'May 2026',
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-31',
            ])->assertCreated();

        $response = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/tax-returns', [
                'tax_type' => 'vat',
                'period_start' => '2026-05-01',
                'period_end' => '2026-05-31',
            ])
            ->assertCreated()
            ->assertJsonPath('data.tax_return.total_payable', '0.0000');

        $taxReturnId = $response->json('data.tax_return.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/tax-returns/{$taxReturnId}/finalize")
            ->assertSuccessful()
            ->assertJsonPath('data.tax_return.status', 'finalized');

        $taxReturn = TaxReturn::findOrFail($taxReturnId);
        expect($taxReturn->journal_entry_id)->toBeNull();
    });

    it('throws validation error if required mappings are missing during non-nil finalization', function (): void {
        [, $token, $companyId, $branchId] = financeActor();

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

        Invoice::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'invoice_number' => 'INV-1',
            'partner_id' => $partner->id,
            'currency_id' => 1,
            'exchange_rate' => 1.0,
            'invoice_date' => '2026-05-10',
            'due_date' => '2026-06-10',
            'status' => 'posted',
            'subtotal' => 1000000.0,
            'tax_total' => 100000.0,
            'total' => 1100000.0,
        ]);

        $response = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/tax-returns', [
                'tax_type' => 'vat',
                'period_start' => '2026-05-01',
                'period_end' => '2026-05-31',
            ])
            ->assertCreated();

        $taxReturnId = $response->json('data.tax_return.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/tax-returns/{$taxReturnId}/finalize")
            ->assertStatus(422);
    });

    it('deletes draft return but rejects deleting finalized return', function (): void {
        [, $token, $companyId] = financeActor();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/periods', [
                'name' => 'May 2026',
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-31',
            ])->assertCreated();

        // Create return 1 (deleted draft)
        $res1 = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/tax-returns', [
                'tax_type' => 'vat',
                'period_start' => '2026-05-01',
                'period_end' => '2026-05-31',
            ])
            ->assertCreated();
        $id1 = $res1->json('data.tax_return.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->deleteJson("/api/v1/finance/tax-returns/{$id1}")
            ->assertSuccessful();

        expect(TaxReturn::find($id1))->toBeNull();

        // Create return 2 (cannot delete finalized)
        $res2 = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/tax-returns', [
                'tax_type' => 'vat',
                'period_start' => '2026-05-01',
                'period_end' => '2026-05-31',
            ])
            ->assertCreated();
        $id2 = $res2->json('data.tax_return.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/tax-returns/{$id2}/finalize")
            ->assertSuccessful();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->deleteJson("/api/v1/finance/tax-returns/{$id2}")
            ->assertStatus(422);

        expect(TaxReturn::find($id2))->not->toBeNull();
    });
});
