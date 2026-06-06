<?php

use App\Models\User;
use App\Modules\Finance\Models\TaxRate;
use App\Modules\Partners\Models\Partner;
use Database\Seeders\CurrencySeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
    $this->seed(CurrencySeeder::class);
});

describe('Finance Invoices', function () {
    it('creates a draft invoice with correct tax and subtotal calculations', function (): void {
        [, $token, $companyId] = financeActor();

        // Create a customer partner
        $partner = Partner::create([
            'company_id' => $companyId,
            'type' => 'customer',
            'name' => 'Acme Corp',
            'code' => 'CUST-001',
            'status' => 'active',
        ]);

        // Create a tax rate (VAT 11%)
        $taxRate = TaxRate::create([
            'company_id' => $companyId,
            'name' => 'PPN 11%',
            'type' => 'vat',
            'rate' => 11.0000,
            'is_active' => true,
        ]);

        $invoiceData = [
            'partner_id' => $partner->id,
            'invoice_date' => '2026-05-22',
            'due_date' => '2026-06-22',
            'notes' => 'Test invoice notes',
            'lines' => [
                [
                    'description' => 'Item A',
                    'quantity' => '2.0000',
                    'unit_price' => '100000.0000',
                    'discount' => '10000.0000',
                    'tax_rate_id' => $taxRate->id,
                ],
                [
                    'description' => 'Item B',
                    'quantity' => '1.0000',
                    'unit_price' => '50000.0000',
                    'discount' => '0.0000',
                    'tax_rate_id' => null,
                ],
            ],
        ];

        // Item A subtotal: (2 * 100,000) - 10,000 = 190,000. Tax: 190,000 * 0.11 = 20,900. Total: 210,900.
        // Item B subtotal: (1 * 50,000) - 0 = 50,000. Tax: 0. Total: 50,000.
        // Invoice totals: Subtotal = 240,000. Tax = 20,900. Total = 260,900.

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/invoices', $invoiceData)
            ->assertCreated()
            ->assertJsonPath('data.invoice.status', 'draft')
            ->assertJsonPath('data.invoice.subtotal', '240000.0000')
            ->assertJsonPath('data.invoice.tax_total', '20900.0000')
            ->assertJsonPath('data.invoice.total', '260900.0000')
            ->assertJsonPath('data.invoice.lines.0.line_subtotal', '190000.0000')
            ->assertJsonPath('data.invoice.lines.0.tax_amount', '20900.0000')
            ->assertJsonPath('data.invoice.lines.0.line_total', '210900.0000')
            ->assertJsonPath('data.invoice.lines.1.line_subtotal', '50000.0000')
            ->assertJsonPath('data.invoice.lines.1.tax_amount', '0.0000')
            ->assertJsonPath('data.invoice.lines.1.line_total', '50000.0000');
    });

    it('updates a draft invoice successfully', function (): void {
        [, $token, $companyId] = financeActor();

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
                'invoice_date' => '2026-05-22',
                'due_date' => '2026-06-22',
                'lines' => [
                    [
                        'description' => 'Item A',
                        'quantity' => '1.0000',
                        'unit_price' => '10000.0000',
                    ],
                ],
            ])
            ->json('data.invoice.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->patchJson("/api/v1/finance/invoices/{$invoiceId}", [
                'notes' => 'Updated notes',
                'lines' => [
                    [
                        'description' => 'Item A Updated',
                        'quantity' => '3.0000',
                        'unit_price' => '10000.0000',
                    ],
                ],
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.invoice.notes', 'Updated notes')
            ->assertJsonPath('data.invoice.total', '30000.0000');
    });

    it('lists and filters invoices', function (): void {
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
            'type' => 'customer',
            'name' => 'Customer 2',
            'code' => 'CUST-002',
            'status' => 'active',
        ]);

        // Invoice 1 for Partner 1
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/invoices', [
                'partner_id' => $partner1->id,
                'invoice_date' => '2026-05-22',
                'due_date' => '2026-06-22',
                'lines' => [['description' => 'Item', 'quantity' => 1, 'unit_price' => 100]],
            ]);

        // Invoice 2 for Partner 2
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/invoices', [
                'partner_id' => $partner2->id,
                'invoice_date' => '2026-05-22',
                'due_date' => '2026-06-22',
                'lines' => [['description' => 'Item', 'quantity' => 1, 'unit_price' => 200]],
            ]);

        // List all
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/finance/invoices')
            ->assertSuccessful()
            ->assertJsonCount(2, 'data.invoices');

        // Filter by partner
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/finance/invoices?partner_id={$partner1->id}")
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.invoices')
            ->assertJsonPath('data.invoices.0.partner_id', $partner1->id);
    });

    it('filters invoices by invoice date range', function (): void {
        [, $token, $companyId] = financeActor();

        $partner = Partner::create([
            'company_id' => $companyId,
            'type' => 'customer',
            'name' => 'Date Filter Customer',
            'code' => 'CUST-DATE',
            'status' => 'active',
        ]);

        foreach ([
            ['date' => '2026-05-31', 'amount' => 100],
            ['date' => '2026-06-15', 'amount' => 200],
            ['date' => '2026-07-01', 'amount' => 300],
        ] as $invoice) {
            $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
                ->postJson('/api/v1/finance/invoices', [
                    'partner_id' => $partner->id,
                    'invoice_date' => $invoice['date'],
                    'due_date' => '2026-07-31',
                    'lines' => [
                        [
                            'description' => 'Date filtered item',
                            'quantity' => 1,
                            'unit_price' => $invoice['amount'],
                        ],
                    ],
                ])->assertCreated();
        }

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/finance/invoices?start_date=2026-06-01&end_date=2026-06-30')
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.invoices')
            ->assertJsonPath('data.invoices.0.invoice_date', '2026-06-15');
    });

    it('rejects invoice list date ranges where the end date is before the start date', function (): void {
        [, $token, $companyId] = financeActor();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/finance/invoices?start_date=2026-06-30&end_date=2026-06-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);
    });

    it('rejects invoice referring to cross-company partner', function (): void {
        [, $tokenA, $companyA] = financeActor();
        [, $tokenB, $companyB] = financeActor();

        $partnerB = Partner::create([
            'company_id' => $companyB,
            'type' => 'customer',
            'name' => 'Acme Corp B',
            'code' => 'CUST-002',
            'status' => 'active',
        ]);

        $this->withToken($tokenA)->withHeader('X-Company-Id', (string) $companyA)
            ->postJson('/api/v1/finance/invoices', [
                'partner_id' => $partnerB->id,
                'invoice_date' => '2026-05-22',
                'due_date' => '2026-06-22',
                'lines' => [
                    [
                        'description' => 'Item A',
                        'quantity' => '1.0000',
                        'unit_price' => '10000.0000',
                    ],
                ],
            ])
            ->assertUnprocessable();
    });

    it('denies access without permission', function (): void {
        $user = User::factory()->create();
        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.access_token');

        $this->withToken($token)
            ->postJson('/api/v1/finance/invoices', [
                'partner_id' => 1,
                'invoice_date' => '2026-05-22',
                'due_date' => '2026-06-22',
                'lines' => [],
            ])
            ->assertForbidden();
    });
});
