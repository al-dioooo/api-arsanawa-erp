<?php

use App\Modules\Finance\Models\ApprovalRequest;
use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Partners\Models\Partner;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
    $this->seed(CurrencySeeder::class);
});

describe('dashboard summaries', function (): void {
    it('returns inventory dashboard counters for the active company', function (): void {
        [, $token, $companyId, $branchId] = inventoryActor();

        $unitId = createUnit($token, $companyId, 'PCS', 'Pieces');
        $productId = createProduct($token, $companyId, [
            'name' => 'Nasi Box',
            'base_uom_id' => $unitId,
            'variants' => [
                ['sku' => 'NB-001', 'name' => 'Regular'],
            ],
        ]);

        $variantId = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/products/{$productId}")
            ->json('data.product.variants.0.id');

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/inventory/stock/receipts', [
                'branch_id' => $branchId,
                'product_variant_id' => $variantId,
                'quantity' => 10,
                'unit_cost' => 15000,
            ])
            ->assertCreated();

        StockTransfer::create([
            'company_id' => $companyId,
            'from_branch_id' => $branchId,
            'to_branch_id' => $branchId,
            'status' => 'draft',
            'created_by' => 1,
        ]);

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/inventory/dashboard')
            ->assertSuccessful()
            ->assertJsonPath('data.counters.products.total', 1)
            ->assertJsonPath('data.counters.products.active', 1)
            ->assertJsonPath('data.counters.stock_lots.active', 1)
            ->assertJsonPath('data.counters.stock_movements.total', 1)
            ->assertJsonPath('data.counters.stock_movements.unsettled', 1);
    });

    it('returns finance dashboard counters for receivables, payables, and approvals', function (): void {
        [, $token, $companyId] = financeActor();

        $customer = Partner::create([
            'company_id' => $companyId,
            'type' => 'customer',
            'name' => 'Customer One',
            'code' => 'CUST-001',
            'status' => 'active',
        ]);
        $vendor = Partner::create([
            'company_id' => $companyId,
            'type' => 'vendor',
            'name' => 'Vendor One',
            'code' => 'VEND-001',
            'status' => 'active',
        ]);

        $invoice = Invoice::create([
            'company_id' => $companyId,
            'invoice_number' => 'INV-001',
            'partner_id' => $customer->id,
            'currency_id' => 1,
            'exchange_rate' => 1,
            'invoice_date' => '2026-05-31',
            'due_date' => '2026-06-30',
            'status' => 'posted',
            'subtotal' => 100000,
            'tax_total' => 0,
            'total' => 100000,
            'amount_paid' => 25000,
        ]);
        $bill = Bill::create([
            'company_id' => $companyId,
            'bill_number' => 'BILL-001',
            'partner_id' => $vendor->id,
            'currency_id' => 1,
            'exchange_rate' => 1,
            'bill_date' => '2026-05-31',
            'due_date' => '2026-06-30',
            'status' => 'partially_paid',
            'subtotal' => 70000,
            'tax_total' => 0,
            'total' => 70000,
            'amount_paid' => 10000,
        ]);

        ApprovalRequest::create([
            'company_id' => $companyId,
            'approvable_type' => Bill::class,
            'approvable_id' => $bill->id,
            'current_level' => 1,
            'status' => 'pending',
        ]);

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/finance/dashboard')
            ->assertSuccessful()
            ->assertJsonPath('data.counters.ar_outstanding', '75000.0000')
            ->assertJsonPath('data.counters.ap_outstanding', '60000.0000')
            ->assertJsonPath('data.counters.pending_approvals', 1)
            ->assertJsonPath('data.recent_activity.0.type', 'bill')
            ->assertJsonPath('data.recent_activity.1.type', 'invoice');
    });

    it('returns daily income and expense buckets for the current month by default', function (): void {
        $this->travelTo(Carbon::parse('2026-06-15 10:00:00'));

        [, $token, $companyId] = financeActor();

        $customer = Partner::create([
            'company_id' => $companyId,
            'type' => 'customer',
            'name' => 'June Customer',
            'code' => 'CUST-JUN',
            'status' => 'active',
        ]);
        $vendor = Partner::create([
            'company_id' => $companyId,
            'type' => 'vendor',
            'name' => 'June Vendor',
            'code' => 'VEND-JUN',
            'status' => 'active',
        ]);

        Invoice::create([
            'company_id' => $companyId,
            'invoice_number' => 'INV-JUN-05',
            'partner_id' => $customer->id,
            'currency_id' => 1,
            'exchange_rate' => 1,
            'invoice_date' => '2026-06-05',
            'due_date' => '2026-06-30',
            'status' => 'posted',
            'subtotal' => 125000,
            'tax_total' => 0,
            'total' => 125000,
            'amount_paid' => 0,
        ]);
        Invoice::create([
            'company_id' => $companyId,
            'invoice_number' => 'INV-MAY-31',
            'partner_id' => $customer->id,
            'currency_id' => 1,
            'exchange_rate' => 1,
            'invoice_date' => '2026-05-31',
            'due_date' => '2026-06-30',
            'status' => 'posted',
            'subtotal' => 999000,
            'tax_total' => 0,
            'total' => 999000,
            'amount_paid' => 0,
        ]);
        Bill::create([
            'company_id' => $companyId,
            'bill_number' => 'BILL-JUN-10',
            'partner_id' => $vendor->id,
            'currency_id' => 1,
            'exchange_rate' => 1,
            'bill_date' => '2026-06-10',
            'due_date' => '2026-06-30',
            'status' => 'posted',
            'subtotal' => 45000,
            'tax_total' => 0,
            'total' => 45000,
            'amount_paid' => 0,
        ]);

        $response = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/finance/dashboard')
            ->assertSuccessful()
            ->assertJsonCount(30, 'data.income_expense_series')
            ->assertJsonPath('data.income_expense_series.0.date', '2026-06-01')
            ->assertJsonPath('data.income_expense_series.4.date', '2026-06-05')
            ->assertJsonPath('data.income_expense_series.9.date', '2026-06-10')
            ->assertJsonPath('data.income_expense_series.29.date', '2026-06-30');

        expect($response->json('data.income_expense_series.4.income'))->toEqual(125000.0)
            ->and($response->json('data.income_expense_series.4.expense'))->toEqual(0.0)
            ->and($response->json('data.income_expense_series.9.income'))->toEqual(0.0)
            ->and($response->json('data.income_expense_series.9.expense'))->toEqual(45000.0);
    });

    it('filters finance dashboard income and expense buckets by requested date range', function (): void {
        [, $token, $companyId] = financeActor();

        $customer = Partner::create([
            'company_id' => $companyId,
            'type' => 'customer',
            'name' => 'Range Customer',
            'code' => 'CUST-RNG',
            'status' => 'active',
        ]);
        $vendor = Partner::create([
            'company_id' => $companyId,
            'type' => 'vendor',
            'name' => 'Range Vendor',
            'code' => 'VEND-RNG',
            'status' => 'active',
        ]);

        Invoice::create([
            'company_id' => $companyId,
            'invoice_number' => 'INV-RNG-IN',
            'partner_id' => $customer->id,
            'currency_id' => 1,
            'exchange_rate' => 1,
            'invoice_date' => '2026-06-12',
            'due_date' => '2026-06-30',
            'status' => 'posted',
            'subtotal' => 210000,
            'tax_total' => 0,
            'total' => 210000,
            'amount_paid' => 0,
        ]);
        Invoice::create([
            'company_id' => $companyId,
            'invoice_number' => 'INV-RNG-OUT',
            'partner_id' => $customer->id,
            'currency_id' => 1,
            'exchange_rate' => 1,
            'invoice_date' => '2026-06-20',
            'due_date' => '2026-06-30',
            'status' => 'posted',
            'subtotal' => 990000,
            'tax_total' => 0,
            'total' => 990000,
            'amount_paid' => 0,
        ]);
        Bill::create([
            'company_id' => $companyId,
            'bill_number' => 'BILL-RNG-IN',
            'partner_id' => $vendor->id,
            'currency_id' => 1,
            'exchange_rate' => 1,
            'bill_date' => '2026-06-14',
            'due_date' => '2026-06-30',
            'status' => 'posted',
            'subtotal' => 70000,
            'tax_total' => 0,
            'total' => 70000,
            'amount_paid' => 0,
        ]);

        $response = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/finance/dashboard?start_date=2026-06-10&end_date=2026-06-15')
            ->assertSuccessful()
            ->assertJsonCount(6, 'data.income_expense_series')
            ->assertJsonPath('data.income_expense_series.0.date', '2026-06-10')
            ->assertJsonPath('data.income_expense_series.5.date', '2026-06-15');

        expect($response->json('data.income_expense_series.2.income'))->toEqual(210000.0)
            ->and($response->json('data.income_expense_series.4.expense'))->toEqual(70000.0);
    });

    it('returns POS dashboard counters for registers, shifts, and open sales', function (): void {
        [, $token, $companyId, $branchId] = financeActor();

        $registerId = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/pos/registers', [
                'name' => 'Front Register',
                'code' => 'FRONT',
                'branch_id' => $branchId,
            ])
            ->assertCreated()
            ->json('data.register.id');

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/pos/shifts/open', [
                'register_id' => $registerId,
                'opening_float' => 100000,
            ])
            ->assertCreated();

        $unitId = createUnit($token, $companyId, 'BOX', 'Box');
        $productId = createProduct($token, $companyId, [
            'name' => 'Snack Box',
            'base_uom_id' => $unitId,
            'variants' => [
                ['sku' => 'SB-001', 'name' => 'Regular'],
            ],
        ]);
        $variantId = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/products/{$productId}")
            ->json('data.product.variants.0.id');

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/pos/sales', [
                'type' => 'counter',
                'branch_id' => $branchId,
                'register_id' => $registerId,
                'lines' => [
                    [
                        'product_variant_id' => $variantId,
                        'quantity' => 1,
                        'unit_price' => 25000,
                    ],
                ],
            ])
            ->assertCreated();

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/pos/dashboard')
            ->assertSuccessful()
            ->assertJsonPath('data.counters.registers.active', 1)
            ->assertJsonPath('data.counters.shifts.open', 1)
            ->assertJsonPath('data.counters.sales.open', 1)
            ->assertJsonPath('data.counters.sales.today_count', 1)
            ->assertJsonPath('data.counters.sales.today_total', '25000.0000');
    });
});
