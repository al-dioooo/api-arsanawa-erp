<?php

use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\Payment;
use App\Modules\Partners\Models\Partner;
use Database\Seeders\CurrencySeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
    $this->seed(CurrencySeeder::class);
});

describe('Finance Payment Posting and Voiding', function () {
    it('posts a draft inbound payment to general ledger generating balanced entry', function (): void {
        [, $token, $companyId] = financeActor();

        // 1. Create open period
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/periods', [
                'name' => 'May 2026',
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-31',
            ])->assertCreated();

        // 2. Create accounts
        $cashAccount = createAccount($token, $companyId, ['code' => '1-1010', 'name' => 'Cash', 'type' => 'asset']);
        $arAccount = createAccount($token, $companyId, ['code' => '1-1200', 'name' => 'AR', 'type' => 'asset']);
        $revenueAccount = createAccount($token, $companyId, ['code' => '4-1100', 'name' => 'Rev', 'type' => 'revenue']);
        $custPrepayAccount = createAccount($token, $companyId, ['code' => '2-2100', 'name' => 'Customer Prepayments', 'type' => 'liability']);

        // 3. Setup mappings
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->putJson('/api/v1/finance/account-mappings', [
                'mappings' => [
                    ['key' => 'accounts_receivable', 'account_id' => $arAccount],
                    ['key' => 'sales_revenue', 'account_id' => $revenueAccount],
                    ['key' => 'customer_prepayments', 'account_id' => $custPrepayAccount],
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

        // 5. Create and post invoice (Total: 1000)
        $invoiceId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/invoices', [
                'partner_id' => $partner->id,
                'invoice_date' => '2026-05-15',
                'due_date' => '2026-06-15',
                'lines' => [['description' => 'Svc', 'quantity' => 1, 'unit_price' => 1000]],
            ])->json('data.invoice.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/invoices/{$invoiceId}/post")
            ->assertSuccessful();

        // 6. Create payment (Amount: 1200, allocation: 1000 to invoice, 200 unallocated/advance)
        $paymentId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/payments', [
                'partner_id' => $partner->id,
                'payment_type' => 'inbound',
                'payment_date' => '2026-05-15',
                'payment_method' => 'cash',
                'amount' => '1200.0000',
                'currency_id' => 1,
                'exchange_rate' => '1.00000000',
                'cash_account_id' => $cashAccount,
                'allocations' => [
                    [
                        'invoice_id' => $invoiceId,
                        'amount' => '1000.0000',
                    ],
                ],
            ])->json('data.payment.id');

        // Post payment
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/payments/{$paymentId}/post")
            ->assertSuccessful()
            ->assertJsonPath('data.payment.status', 'posted');

        // Check invoice status & amount_paid
        $invoice = Invoice::findOrFail($invoiceId);
        expect((float) $invoice->amount_paid)->toBe(1000.0)
            ->and($invoice->status)->toBe('paid');

        // Verify Journal entry
        $payment = Payment::findOrFail($paymentId);
        expect($payment->journal_entry_id)->not->toBeNull();

        $entryResponse = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/finance/journal-entries/{$payment->journal_entry_id}")
            ->assertSuccessful();

        $lines = $entryResponse->json('data.journal_entry.lines');
        expect(count($lines))->toBe(3);

        // Debit Cash: 1200
        $cashLine = collect($lines)->firstWhere('account_id', $cashAccount);
        expect((float) $cashLine['debit'])->toBe(1200.0)
            ->and((float) $cashLine['credit'])->toBe(0.0);

        // Credit AR: 1000
        $arLine = collect($lines)->firstWhere('account_id', $arAccount);
        expect((float) $arLine['debit'])->toBe(0.0)
            ->and((float) $arLine['credit'])->toBe(1000.0);

        // Credit Customer Prepayment: 200
        $prepayLine = collect($lines)->firstWhere('account_id', $custPrepayAccount);
        expect((float) $prepayLine['debit'])->toBe(0.0)
            ->and((float) $prepayLine['credit'])->toBe(200.0);
    });

    it('posts a draft outbound payment to general ledger generating balanced entry', function (): void {
        [, $token, $companyId] = financeActor();

        // 1. Create open period
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/periods', [
                'name' => 'May 2026',
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-31',
            ])->assertCreated();

        // 2. Create accounts
        $cashAccount = createAccount($token, $companyId, ['code' => '1-1010', 'name' => 'Cash', 'type' => 'asset']);
        $apAccount = createAccount($token, $companyId, ['code' => '2-1100', 'name' => 'AP', 'type' => 'liability']);
        $expenseAccount = createAccount($token, $companyId, ['code' => '5-1100', 'name' => 'Exp', 'type' => 'expense']);
        $vendorPrepayAccount = createAccount($token, $companyId, ['code' => '1-2200', 'name' => 'Vendor Prepayments', 'type' => 'asset']);

        // 3. Setup mappings
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->putJson('/api/v1/finance/account-mappings', [
                'mappings' => [
                    ['key' => 'accounts_payable', 'account_id' => $apAccount],
                    ['key' => 'purchase_expense', 'account_id' => $expenseAccount],
                    ['key' => 'vendor_prepayments', 'account_id' => $vendorPrepayAccount],
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

        // 5. Create and post bill (Total: 800)
        $billId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/bills', [
                'partner_id' => $partner->id,
                'bill_date' => '2026-05-15',
                'due_date' => '2026-06-15',
                'lines' => [['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 800]],
            ])->json('data.bill.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/bills/{$billId}/post")
            ->assertSuccessful();

        // 6. Create payment (Amount: 1000, allocation: 800 to bill, 200 unallocated/advance)
        $paymentId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/payments', [
                'partner_id' => $partner->id,
                'payment_type' => 'outbound',
                'payment_date' => '2026-05-15',
                'payment_method' => 'cash',
                'amount' => '1000.0000',
                'currency_id' => 1,
                'exchange_rate' => '1.00000000',
                'cash_account_id' => $cashAccount,
                'allocations' => [
                    [
                        'bill_id' => $billId,
                        'amount' => '800.0000',
                    ],
                ],
            ])->json('data.payment.id');

        // Post payment
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/payments/{$paymentId}/post")
            ->assertSuccessful()
            ->assertJsonPath('data.payment.status', 'posted');

        // Check bill status & amount_paid
        $bill = Bill::findOrFail($billId);
        expect((float) $bill->amount_paid)->toBe(800.0)
            ->and($bill->status)->toBe('paid');

        // Verify Journal entry
        $payment = Payment::findOrFail($paymentId);
        expect($payment->journal_entry_id)->not->toBeNull();

        $entryResponse = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/finance/journal-entries/{$payment->journal_entry_id}")
            ->assertSuccessful();

        $lines = $entryResponse->json('data.journal_entry.lines');
        expect(count($lines))->toBe(3);

        // Credit Cash: 1000
        $cashLine = collect($lines)->firstWhere('account_id', $cashAccount);
        expect((float) $cashLine['debit'])->toBe(0.0)
            ->and((float) $cashLine['credit'])->toBe(1000.0);

        // Debit AP: 800
        $apLine = collect($lines)->firstWhere('account_id', $apAccount);
        expect((float) $apLine['debit'])->toBe(800.0)
            ->and((float) $apLine['credit'])->toBe(0.0);

        // Debit Vendor Prepayment: 200
        $prepayLine = collect($lines)->firstWhere('account_id', $vendorPrepayAccount);
        expect((float) $prepayLine['debit'])->toBe(200.0)
            ->and((float) $prepayLine['credit'])->toBe(0.0);
    });

    it('rejects posting if prepayment mapping is missing and unallocated amount exists', function (): void {
        [, $token, $companyId] = financeActor();

        // 1. Create open period
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/periods', [
                'name' => 'May 2026',
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-31',
            ])->assertCreated();

        // 2. Create accounts
        $cashAccount = createAccount($token, $companyId, ['code' => '1-1010', 'name' => 'Cash', 'type' => 'asset']);
        $arAccount = createAccount($token, $companyId, ['code' => '1-1200', 'name' => 'AR', 'type' => 'asset']);

        // 3. Setup mappings (missing customer_prepayments)
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->putJson('/api/v1/finance/account-mappings', [
                'mappings' => [
                    ['key' => 'accounts_receivable', 'account_id' => $arAccount],
                ],
            ])->assertSuccessful();

        $partner = Partner::create([
            'company_id' => $companyId,
            'type' => 'customer',
            'name' => 'Acme Corp',
            'code' => 'CUST-001',
            'status' => 'active',
        ]);

        // Create payment (Amount: 500, no allocations -> represents 500 advance)
        $paymentId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/payments', [
                'partner_id' => $partner->id,
                'payment_type' => 'inbound',
                'payment_date' => '2026-05-15',
                'payment_method' => 'cash',
                'amount' => '500.0000',
                'currency_id' => 1,
                'exchange_rate' => '1.00000000',
                'cash_account_id' => $cashAccount,
            ])->json('data.payment.id');

        // Post should fail because customer_prepayments mapping is missing
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/payments/{$paymentId}/post")
            ->assertUnprocessable();
    });

    it('voids a posted payment by generating reversing entries and restoring invoice/bill unpaid balances', function (): void {
        [, $token, $companyId] = financeActor();

        // 1. Create open period
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/periods', [
                'name' => 'May 2026',
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-31',
            ])->assertCreated();

        // 2. Create accounts
        $cashAccount = createAccount($token, $companyId, ['code' => '1-1010', 'name' => 'Cash', 'type' => 'asset']);
        $arAccount = createAccount($token, $companyId, ['code' => '1-1200', 'name' => 'AR', 'type' => 'asset']);
        $revenueAccount = createAccount($token, $companyId, ['code' => '4-1100', 'name' => 'Rev', 'type' => 'revenue']);

        // 3. Setup mappings
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

        // 4. Create and post invoice (Total: 1000)
        $invoiceId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/invoices', [
                'partner_id' => $partner->id,
                'invoice_date' => '2026-05-15',
                'due_date' => '2026-06-15',
                'lines' => [['description' => 'Svc', 'quantity' => 1, 'unit_price' => 1000]],
            ])->json('data.invoice.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/invoices/{$invoiceId}/post")
            ->assertSuccessful();

        // 5. Create and post payment (Amount: 1000, fully allocated)
        $paymentId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/payments', [
                'partner_id' => $partner->id,
                'payment_type' => 'inbound',
                'payment_date' => '2026-05-15',
                'payment_method' => 'cash',
                'amount' => '1000.0000',
                'currency_id' => 1,
                'exchange_rate' => '1.00000000',
                'cash_account_id' => $cashAccount,
                'allocations' => [
                    [
                        'invoice_id' => $invoiceId,
                        'amount' => '1000.0000',
                    ],
                ],
            ])->json('data.payment.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/payments/{$paymentId}/post")
            ->assertSuccessful();

        // 6. Void payment
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/payments/{$paymentId}/void")
            ->assertSuccessful()
            ->assertJsonPath('data.payment.status', 'void');

        // Check invoice status & amount_paid is restored
        $invoice = Invoice::findOrFail($invoiceId);
        expect((float) $invoice->amount_paid)->toBe(0.0)
            ->and($invoice->status)->toBe('posted'); // returns to posted (which is unpaid)

        // Check journal entry is void
        $payment = Payment::findOrFail($paymentId);
        $entryResponse = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/finance/journal-entries/{$payment->journal_entry_id}")
            ->json('data.journal_entry');
        expect($entryResponse['status'])->toBe('void');
    });
});
