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

describe('Finance Bills', function () {
    it('creates a draft bill with correct VAT and withholding calculations', function (): void {
        [, $token, $companyId] = financeActor();

        // Create a vendor partner
        $partner = Partner::create([
            'company_id' => $companyId,
            'type' => 'vendor',
            'name' => 'Supplier Inc',
            'code' => 'SUP-001',
            'status' => 'active',
        ]);

        // Create VAT 11%
        $vatRate = TaxRate::create([
            'company_id' => $companyId,
            'name' => 'PPN 11%',
            'type' => 'vat',
            'rate' => 11.0000,
            'is_active' => true,
        ]);

        // Create Withholding 2%
        $whtRate = TaxRate::create([
            'company_id' => $companyId,
            'name' => 'PPH 23 2%',
            'type' => 'withholding',
            'rate' => 2.0000,
            'is_active' => true,
        ]);

        $billData = [
            'partner_id' => $partner->id,
            'bill_date' => '2026-05-22',
            'due_date' => '2026-06-22',
            'notes' => 'Test bill notes',
            'lines' => [
                [
                    'description' => 'Item A (VAT)',
                    'quantity' => '2.0000',
                    'unit_price' => '50000.0000',
                    'discount' => '0.0000',
                    'tax_rate_id' => $vatRate->id,
                ],
                [
                    'description' => 'Item B (WHT)',
                    'quantity' => '1.0000',
                    'unit_price' => '200000.0000',
                    'discount' => '0.0000',
                    'tax_rate_id' => $whtRate->id,
                ],
            ],
        ];

        // Line 1: (2 * 50,000) = 100,000. VAT: 11,000. WHT: 0. Total: 111,000.
        // Line 2: (1 * 200,000) = 200,000. VAT: 0. WHT: 4,000. Total: 196,000.
        // Bill totals: Subtotal = 300,000. Tax = 11,000. WHT = 4,000. Total = 307,000.

        $response = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/bills', $billData)
            ->assertCreated()
            ->assertJsonPath('data.bill.status', 'draft')
            ->assertJsonPath('data.bill.subtotal', '300000.0000')
            ->assertJsonPath('data.bill.tax_total', '11000.0000')
            ->assertJsonPath('data.bill.withholding_total', '4000.0000')
            ->assertJsonPath('data.bill.total', '307000.0000')
            ->assertJsonPath('data.bill.lines.0.line_subtotal', '100000.0000')
            ->assertJsonPath('data.bill.lines.0.tax_amount', '11000.0000')
            ->assertJsonPath('data.bill.lines.0.withholding_amount', '0.0000')
            ->assertJsonPath('data.bill.lines.0.line_total', '111000.0000')
            ->assertJsonPath('data.bill.lines.1.line_subtotal', '200000.0000')
            ->assertJsonPath('data.bill.lines.1.tax_amount', '0.0000')
            ->assertJsonPath('data.bill.lines.1.withholding_amount', '4000.0000')
            ->assertJsonPath('data.bill.lines.1.line_total', '196000.0000');

        expect($response->json('data.bill.bill_number'))->toStartWith('BILL-');
    });

    it('updates a draft bill successfully', function (): void {
        [, $token, $companyId] = financeActor();

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
                'bill_date' => '2026-05-22',
                'due_date' => '2026-06-22',
                'lines' => [
                    [
                        'description' => 'Item A',
                        'quantity' => '1.0000',
                        'unit_price' => '10000.0000',
                    ],
                ],
            ])
            ->json('data.bill.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->patchJson("/api/v1/finance/bills/{$billId}", [
                'notes' => 'Updated bill notes',
                'lines' => [
                    [
                        'description' => 'Item A Updated',
                        'quantity' => '3.0000',
                        'unit_price' => '12000.0000',
                    ],
                ],
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.bill.notes', 'Updated bill notes')
            ->assertJsonPath('data.bill.total', '36000.0000');
    });

    it('lists and filters bills', function (): void {
        [, $token, $companyId] = financeActor();

        $partner1 = Partner::create([
            'company_id' => $companyId,
            'type' => 'vendor',
            'name' => 'Supplier 1',
            'code' => 'SUP-001',
            'status' => 'active',
        ]);

        $partner2 = Partner::create([
            'company_id' => $companyId,
            'type' => 'vendor',
            'name' => 'Supplier 2',
            'code' => 'SUP-002',
            'status' => 'active',
        ]);

        // Bill 1 for Partner 1
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/bills', [
                'partner_id' => $partner1->id,
                'bill_date' => '2026-05-22',
                'due_date' => '2026-06-22',
                'lines' => [['description' => 'Item', 'quantity' => 1, 'unit_price' => 100]],
            ]);

        // Bill 2 for Partner 2
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/bills', [
                'partner_id' => $partner2->id,
                'bill_date' => '2026-05-22',
                'due_date' => '2026-06-22',
                'lines' => [['description' => 'Item', 'quantity' => 1, 'unit_price' => 200]],
            ]);

        // List all
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/finance/bills')
            ->assertSuccessful()
            ->assertJsonCount(2, 'data.bills');

        // Filter by partner
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/finance/bills?partner_id={$partner1->id}")
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.bills')
            ->assertJsonPath('data.bills.0.partner_id', $partner1->id);
    });

    it('rejects bill referring to cross-company partner', function (): void {
        [, $tokenA, $companyA] = financeActor();
        [, $tokenB, $companyB] = financeActor();

        $partnerB = Partner::create([
            'company_id' => $companyB,
            'type' => 'vendor',
            'name' => 'Supplier B',
            'code' => 'SUP-002',
            'status' => 'active',
        ]);

        $this->withToken($tokenA)->withHeader('X-Company-Id', (string) $companyA)
            ->postJson('/api/v1/finance/bills', [
                'partner_id' => $partnerB->id,
                'bill_date' => '2026-05-22',
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
            ->postJson('/api/v1/finance/bills', [
                'partner_id' => 1,
                'bill_date' => '2026-05-22',
                'due_date' => '2026-06-22',
                'lines' => [],
            ])
            ->assertForbidden();
    });
});
