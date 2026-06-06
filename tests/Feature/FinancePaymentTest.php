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

describe('Finance Payments CRUD and Validation', function () {
    it('rejects stale frontend payment payload aliases', function (): void {
        [, $token, $companyId] = financeActor();

        $partner = Partner::create([
            'company_id' => $companyId,
            'type' => 'vendor',
            'name' => 'Supplier Inc',
            'code' => 'SUP-001',
            'status' => 'active',
        ]);

        $cashAccount = createAccount($token, $companyId, [
            'code' => '1-1010',
            'name' => 'Cash in Hand',
            'type' => 'asset',
        ]);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/payments', [
                'partner_id' => $partner->id,
                'account_id' => $cashAccount,
                'payment_type' => 'outgoing',
                'payment_date' => '2026-05-22',
                'payment_method' => 'cash',
                'amount' => '500.0000',
                'currency_id' => 1,
                'exchange_rate' => '1.00000000',
                'allocations' => [
                    [
                        'allocatable_type' => 'bill',
                        'allocatable_id' => 123,
                        'amount' => '500.0000',
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_type', 'cash_account_id']);
    });

    it('creates a draft inbound payment with allocations', function (): void {
        [, $token, $companyId] = financeActor();

        $partner = Partner::create([
            'company_id' => $companyId,
            'type' => 'customer',
            'name' => 'Acme Corp',
            'code' => 'CUST-001',
            'status' => 'active',
        ]);

        $cashAccount = createAccount($token, $companyId, [
            'code' => '1-1010',
            'name' => 'Cash in Hand',
            'type' => 'asset',
        ]);

        // Create an invoice draft to allocate
        $invoiceResponse = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/invoices', [
                'partner_id' => $partner->id,
                'invoice_date' => '2026-05-22',
                'due_date' => '2026-06-22',
                'lines' => [
                    [
                        'description' => 'Service A',
                        'quantity' => '1.0000',
                        'unit_price' => '1000.0000',
                    ],
                ],
            ])->assertCreated();

        $invoiceId = $invoiceResponse->json('data.invoice.id');

        // Post the invoice first so it can receive allocations (it must be posted or partially_paid)
        // Wait, posting invoice requires open period and mappings. Let's set that up.
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

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/invoices/{$invoiceId}/post")
            ->assertSuccessful();

        $paymentData = [
            'partner_id' => $partner->id,
            'payment_type' => 'inbound',
            'payment_date' => '2026-05-22',
            'payment_method' => 'cash',
            'amount' => '1200.0000',
            'currency_id' => 1, // USD (seeded by CurrencySeeder)
            'exchange_rate' => '1.00000000',
            'cash_account_id' => $cashAccount,
            'notes' => 'Inbound payment notes',
            'allocations' => [
                [
                    'invoice_id' => $invoiceId,
                    'amount' => '1000.0000',
                ],
            ],
        ];

        $response = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/payments', $paymentData)
            ->assertCreated()
            ->assertJsonPath('data.payment.status', 'draft')
            ->assertJsonPath('data.payment.amount', '1200.0000')
            ->assertJsonPath('data.payment.allocations.0.invoice_id', $invoiceId)
            ->assertJsonPath('data.payment.allocations.0.amount', '1000.0000');

        expect($response->json('data.payment.payment_number'))->toStartWith('PAY-');
    });

    it('creates a draft outbound payment with bill allocations', function (): void {
        [, $token, $companyId] = financeActor();

        $partner = Partner::create([
            'company_id' => $companyId,
            'type' => 'vendor',
            'name' => 'Supplier Inc',
            'code' => 'SUP-001',
            'status' => 'active',
        ]);

        $cashAccount = createAccount($token, $companyId, [
            'code' => '1-1010',
            'name' => 'Cash in Hand',
            'type' => 'asset',
        ]);

        // Setup periods & mappings for bill
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

        $billId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/bills', [
                'partner_id' => $partner->id,
                'bill_date' => '2026-05-22',
                'due_date' => '2026-06-22',
                'lines' => [
                    [
                        'description' => 'Consulting',
                        'quantity' => '1.0000',
                        'unit_price' => '800.0000',
                    ],
                ],
            ])->json('data.bill.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/bills/{$billId}/post")
            ->assertSuccessful();

        $paymentData = [
            'partner_id' => $partner->id,
            'payment_type' => 'outbound',
            'payment_date' => '2026-05-22',
            'payment_method' => 'bank_transfer',
            'amount' => '800.0000',
            'currency_id' => 1,
            'exchange_rate' => '1.00000000',
            'cash_account_id' => $cashAccount,
            'allocations' => [
                [
                    'bill_id' => $billId,
                    'amount' => '800.0000',
                ],
            ],
        ];

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/payments', $paymentData)
            ->assertCreated()
            ->assertJsonPath('data.payment.amount', '800.0000')
            ->assertJsonPath('data.payment.allocations.0.bill_id', $billId)
            ->assertJsonPath('data.payment.allocations.0.amount', '800.0000');
    });

    it('updates a draft payment and its allocations', function (): void {
        [, $token, $companyId] = financeActor();

        $partner = Partner::create([
            'company_id' => $companyId,
            'type' => 'customer',
            'name' => 'Acme Corp',
            'code' => 'CUST-001',
            'status' => 'active',
        ]);

        $cashAccount = createAccount($token, $companyId, ['code' => '1-1010', 'name' => 'Cash', 'type' => 'asset']);

        $paymentId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/payments', [
                'partner_id' => $partner->id,
                'payment_type' => 'inbound',
                'payment_date' => '2026-05-22',
                'payment_method' => 'cash',
                'amount' => '500.0000',
                'currency_id' => 1,
                'exchange_rate' => '1.00000000',
                'cash_account_id' => $cashAccount,
            ])->json('data.payment.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->patchJson("/api/v1/finance/payments/{$paymentId}", [
                'amount' => '600.0000',
                'notes' => 'Updated notes',
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.payment.amount', '600.0000')
            ->assertJsonPath('data.payment.notes', 'Updated notes');
    });

    it('lists and filters payments', function (): void {
        [, $token, $companyId] = financeActor();

        $partner1 = Partner::create([
            'company_id' => $companyId,
            'type' => 'customer',
            'name' => 'Customer 1',
            'code' => 'CUST-001',
            'status' => 'active',
        ]);

        $partner2 = Partner::create([
            'company_id' => $companyId,
            'type' => 'vendor',
            'name' => 'Vendor 1',
            'code' => 'SUP-001',
            'status' => 'active',
        ]);

        $cashAccount = createAccount($token, $companyId, ['code' => '1-1010', 'name' => 'Cash', 'type' => 'asset']);

        // Payment 1
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/payments', [
                'partner_id' => $partner1->id,
                'payment_type' => 'inbound',
                'payment_date' => '2026-05-22',
                'payment_method' => 'cash',
                'amount' => '100.0000',
                'currency_id' => 1,
                'exchange_rate' => '1.00000000',
                'cash_account_id' => $cashAccount,
            ])->assertCreated();

        // Payment 2
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/payments', [
                'partner_id' => $partner2->id,
                'payment_type' => 'outbound',
                'payment_date' => '2026-05-22',
                'payment_method' => 'cash',
                'amount' => '200.0000',
                'currency_id' => 1,
                'exchange_rate' => '1.00000000',
                'cash_account_id' => $cashAccount,
            ])->assertCreated();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/finance/payments')
            ->assertSuccessful()
            ->assertJsonCount(2, 'data.payments');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/finance/payments?partner_id={$partner1->id}")
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.payments')
            ->assertJsonPath('data.payments.0.partner_id', $partner1->id);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/finance/payments?payment_type=outbound')
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.payments')
            ->assertJsonPath('data.payments.0.payment_type', 'outbound');
    });

    it('filters outbound payments by payment date range', function (): void {
        [, $token, $companyId] = financeActor();

        $partner = Partner::create([
            'company_id' => $companyId,
            'type' => 'vendor',
            'name' => 'Date Filter Vendor',
            'code' => 'SUP-DATE',
            'status' => 'active',
        ]);

        $cashAccount = createAccount($token, $companyId, ['code' => '1-1010', 'name' => 'Cash', 'type' => 'asset']);

        foreach ([
            ['date' => '2026-05-31', 'amount' => '100.0000'],
            ['date' => '2026-06-15', 'amount' => '200.0000'],
            ['date' => '2026-07-01', 'amount' => '300.0000'],
        ] as $payment) {
            $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
                ->postJson('/api/v1/finance/payments', [
                    'partner_id' => $partner->id,
                    'payment_type' => 'outbound',
                    'payment_date' => $payment['date'],
                    'payment_method' => 'cash',
                    'amount' => $payment['amount'],
                    'currency_id' => 1,
                    'exchange_rate' => '1.00000000',
                    'cash_account_id' => $cashAccount,
                ])->assertCreated();
        }

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/finance/payments?payment_type=outbound&start_date=2026-06-01&end_date=2026-06-30')
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.payments')
            ->assertJsonPath('data.payments.0.payment_type', 'outbound')
            ->assertJsonPath('data.payments.0.payment_date', '2026-06-15');
    });

    it('filters inbound receipts by payment date range', function (): void {
        [, $token, $companyId] = financeActor();

        $partner = Partner::create([
            'company_id' => $companyId,
            'type' => 'customer',
            'name' => 'Date Filter Customer',
            'code' => 'CUST-DATE',
            'status' => 'active',
        ]);

        $cashAccount = createAccount($token, $companyId, ['code' => '1-1010', 'name' => 'Cash', 'type' => 'asset']);

        foreach ([
            ['date' => '2026-05-31', 'amount' => '100.0000'],
            ['date' => '2026-06-15', 'amount' => '200.0000'],
            ['date' => '2026-07-01', 'amount' => '300.0000'],
        ] as $receipt) {
            $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
                ->postJson('/api/v1/finance/payments', [
                    'partner_id' => $partner->id,
                    'payment_type' => 'inbound',
                    'payment_date' => $receipt['date'],
                    'payment_method' => 'cash',
                    'amount' => $receipt['amount'],
                    'currency_id' => 1,
                    'exchange_rate' => '1.00000000',
                    'cash_account_id' => $cashAccount,
                ])->assertCreated();
        }

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/finance/payments?payment_type=inbound&start_date=2026-06-01&end_date=2026-06-30')
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.payments')
            ->assertJsonPath('data.payments.0.payment_type', 'inbound')
            ->assertJsonPath('data.payments.0.payment_date', '2026-06-15');
    });

    it('rejects payment list date ranges where the end date is before the start date', function (): void {
        [, $token, $companyId] = financeActor();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/finance/payments?start_date=2026-06-30&end_date=2026-06-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);
    });

    it('rejects allocations exceeding invoice/bill remaining balance', function (): void {
        [, $token, $companyId] = financeActor();

        $partner = Partner::create([
            'company_id' => $companyId,
            'type' => 'customer',
            'name' => 'Acme Corp',
            'code' => 'CUST-001',
            'status' => 'active',
        ]);

        $cashAccount = createAccount($token, $companyId, ['code' => '1-1010', 'name' => 'Cash', 'type' => 'asset']);

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

        $invoiceId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/invoices', [
                'partner_id' => $partner->id,
                'invoice_date' => '2026-05-22',
                'due_date' => '2026-06-22',
                'lines' => [['description' => 'Svc', 'quantity' => 1, 'unit_price' => 500]],
            ])->json('data.invoice.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/invoices/{$invoiceId}/post")
            ->assertSuccessful();

        // Invoice total is 500. Try to allocate 600.
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/payments', [
                'partner_id' => $partner->id,
                'payment_type' => 'inbound',
                'payment_date' => '2026-05-22',
                'payment_method' => 'cash',
                'amount' => '600.0000',
                'currency_id' => 1,
                'exchange_rate' => '1.00000000',
                'cash_account_id' => $cashAccount,
                'allocations' => [
                    [
                        'invoice_id' => $invoiceId,
                        'amount' => '600.0000',
                    ],
                ],
            ])->assertUnprocessable();
    });

    it('rejects allocations that total more than payment amount', function (): void {
        [, $token, $companyId] = financeActor();

        $partner = Partner::create([
            'company_id' => $companyId,
            'type' => 'customer',
            'name' => 'Acme Corp',
            'code' => 'CUST-001',
            'status' => 'active',
        ]);

        $cashAccount = createAccount($token, $companyId, ['code' => '1-1010', 'name' => 'Cash', 'type' => 'asset']);

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

        $invoiceId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/invoices', [
                'partner_id' => $partner->id,
                'invoice_date' => '2026-05-22',
                'due_date' => '2026-06-22',
                'lines' => [['description' => 'Svc', 'quantity' => 1, 'unit_price' => 500]],
            ])->json('data.invoice.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/invoices/{$invoiceId}/post")
            ->assertSuccessful();

        // Payment is 400. Try to allocate 500.
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/payments', [
                'partner_id' => $partner->id,
                'payment_type' => 'inbound',
                'payment_date' => '2026-05-22',
                'payment_method' => 'cash',
                'amount' => '400.0000',
                'currency_id' => 1,
                'exchange_rate' => '1.00000000',
                'cash_account_id' => $cashAccount,
                'allocations' => [
                    [
                        'invoice_id' => $invoiceId,
                        'amount' => '500.0000',
                    ],
                ],
            ])->assertUnprocessable();
    });
});
