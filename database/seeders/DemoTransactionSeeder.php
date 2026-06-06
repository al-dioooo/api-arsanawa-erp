<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\AccountingPeriod;
use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Models\BillLine;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Models\JournalLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Inventory\Models\ProductVariant;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\ExternalApiKey;
use App\Modules\Partners\Models\Partner;
use App\Modules\Partners\Models\PartnerAddress;
use App\Modules\Partners\Models\PartnerContact;
use App\Modules\Platform\Services\SettingsManager;
use App\Modules\Pos\Models\CashierShift;
use App\Modules\Pos\Models\Register;
use App\Modules\Pos\Models\Sale;
use App\Modules\Pos\Models\SaleLine;
use App\Modules\Pos\Models\SalePayment;
use Illuminate\Database\Seeder;

/**
 * Demo Transaction Seeder
 *
 * Populates rich transactional data so that every FR from the academic report
 * (FR01–FR38) is demonstrable on a fresh install:
 *
 * FR01–02  Auth         → sekalori user (created by DatabaseSeeder)
 * FR03–08  POS orders   → shifts, sales in all statuses, sale lines, payments
 * FR04     WhatsApp     → company settings + customers with phone numbers
 * FR09     Finance hub  → accounting periods (FinanceDemoSeeder) + journals
 * FR10–13  Invoices/AR  → posted/draft/voided invoices with filters
 * FR14–17  Bills/AP     → posted/draft/voided bills
 * FR17     Void bill    → one voided bill
 * FR18–19  COA          → accounts (FinanceDemoSeeder)
 * FR20–21  Expense filt → bills across periods and accounts
 * FR22     Export exp.  → outbound Payments (status=posted)
 * FR23–28  Inventory    → products/stock (SekaloriInventoryDemoSeeder)
 * FR29–30  P&L report   → posted journal entries (revenue + expense)
 * FR31     Export inc.  → inbound Payments + completed POS Sales
 * FR32     Browse batch → ExternalApiKey issued + public products in Inventory
 * FR33     Submit order → ExternalApiKey (catering order API)
 * FR34–38  Menu mgmt   → products (SekaloriInventoryDemoSeeder)
 */
class DemoTransactionSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('slug', 'sekalori')->first();
        if ($company === null) {
            $this->command->warn('DemoTransactionSeeder: SEKALORI company not found — skipping.');

            return;
        }

        $branch = Branch::query()->where('company_id', $company->id)->orderBy('id')->first();
        $owner  = User::query()->where('username', 'sekalori')->first() ?? User::query()->first();

        if ($branch === null || $owner === null) {
            $this->command->warn('DemoTransactionSeeder: branch or owner missing — skipping.');

            return;
        }

        $cid = $company->id;
        $bid = $branch->id;
        $uid = $owner->id;

        // ── Step 1: WhatsApp settings (FR04) ──────────────────────────────────────
        $this->seedWhatsAppSettings($company, $owner);

        // ── Step 2: Partners (customers + suppliers) ──────────────────────────────
        $partners = $this->seedPartners($cid, $uid);

        // ── Step 3: External API key (FR32–33) ────────────────────────────────────
        $this->seedExternalApiKey($company, $owner);

        // ── Step 4: POS cashier shifts + sales (FR03–08, FR31) ───────────────────
        $this->seedPosData($cid, $bid, $uid, $partners);

        // ── Step 5: Finance invoices / AR (FR10–13, FR31) ────────────────────────
        $this->seedInvoices($cid, $bid, $uid, $partners);

        // ── Step 6: Finance bills / AP (FR14–17, FR20–21, FR22) ──────────────────
        $this->seedBills($cid, $bid, $uid, $partners);

        // ── Step 7: Finance payments (FR22, FR31) ─────────────────────────────────
        $this->seedPayments($cid, $bid, $uid, $partners);

        // ── Step 8: Journal entries (FR09, FR29–30, income statement) ─────────────
        $this->seedJournalEntries($cid, $bid, $uid);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    //  WhatsApp settings (FR04)
    // ─────────────────────────────────────────────────────────────────────────────

    private function seedWhatsAppSettings(Company $company, User $owner): void
    {
        $settings = app(SettingsManager::class);
        $token    = env('WHATSAPP_FONNTE_TOKEN', '');

        // Enable only when a real token is present in .env so a fresh install
        // does not try to hit Fonnte with an empty key.
        $settings->set($company->id, 'whatsapp', 'enabled', $token !== '', null, $owner->id);

        if ($token !== '') {
            $settings->set($company->id, 'whatsapp', 'token', $token, null, $owner->id);
        }

        $defaultTemplate = implode("\n", [
            'Halo {customer},',
            '',
            'Terima kasih telah memesan di *{company}*!',
            'Nomor pesanan Anda: *{sale_number}*',
            'Total pembayaran: *{total}*',
            'Tanggal: {date}',
            '',
            'Pesanan Anda sedang kami proses. Sampai jumpa!',
        ]);

        $settings->set($company->id, 'whatsapp', 'receipt_template', $defaultTemplate, null, $owner->id);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    //  Partners (customers + suppliers)
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * @return array{customers: array<string, Partner>, suppliers: array<string, Partner>}
     */
    private function seedPartners(int $cid, int $uid): array
    {
        $customers = [
            'CUST-001' => [
                'name' => 'Budi Santoso',
                'phone' => '081234567890',    // normalises → 6281234567890 (FR04 WA)
                'email' => 'budi@sekalori.test',
                'address' => 'Jl. Merdeka No. 12, Bandung, Jawa Barat 40111',
            ],
            'CUST-002' => [
                'name' => 'Siti Rahayu',
                'phone' => '082345678901',
                'email' => 'siti@sekalori.test',
                'address' => 'Jl. Sudirman No. 45, Jakarta Pusat 10220',
            ],
            'CUST-003' => [
                'name' => 'Ahmad Fauzi',
                'phone' => '083456789012',
                'email' => 'ahmad@sekalori.test',
                'address' => 'Jl. Gajah Mada No. 7, Surabaya, Jawa Timur 60175',
            ],
            'CUST-004' => [
                'name' => 'Dewi Lestari',
                'phone' => '084567890123',
                'email' => 'dewi@sekalori.test',
                'address' => 'Jl. Diponegoro No. 33, Yogyakarta 55223',
            ],
        ];

        $suppliers = [
            'SUPP-001' => [
                'name' => 'CV Bumbu Nusantara',
                'phone' => '02155512345',
                'email' => 'bumbu@nusantara.test',
                'address' => 'Kawasan Industri Pulogadung, Jakarta Timur 13930',
            ],
            'SUPP-002' => [
                'name' => 'PT Fresh Ingredients',
                'phone' => null,
                'email' => 'fresh@ingredients.test',
                'address' => 'Pasar Induk Caringin, Bandung 40223',
            ],
        ];

        $result = ['customers' => [], 'suppliers' => []];

        foreach ($customers as $code => $data) {
            $partner = Partner::query()->firstOrCreate(
                ['company_id' => $cid, 'code' => $code],
                [
                    'type'       => 'customer',
                    'name'       => $data['name'],
                    'phone'      => $data['phone'],
                    'email'      => $data['email'],
                    'status'     => 'active',
                    'created_by' => $uid,
                    'updated_by' => $uid,
                ],
            );

            $this->ensureAddress($partner, $data['address']);
            $this->ensureContact($partner, $data['name'], $data['phone'], $data['email']);

            $result['customers'][$code] = $partner;
        }

        foreach ($suppliers as $code => $data) {
            $partner = Partner::query()->firstOrCreate(
                ['company_id' => $cid, 'code' => $code],
                [
                    'type'       => 'supplier',
                    'name'       => $data['name'],
                    'phone'      => $data['phone'],
                    'email'      => $data['email'],
                    'status'     => 'active',
                    'created_by' => $uid,
                    'updated_by' => $uid,
                ],
            );

            $this->ensureAddress($partner, $data['address']);

            $result['suppliers'][$code] = $partner;
        }

        return $result;
    }

    private function ensureAddress(Partner $partner, string $line1): void
    {
        if ($partner->addresses()->exists()) {
            return;
        }

        PartnerAddress::query()->create([
            'partner_id'    => $partner->id,
            'type'          => 'billing',
            'label'         => 'Main Address',
            'address_line_1' => $line1,
            'country'       => 'ID',
            'is_default'    => true,
        ]);
    }

    private function ensureContact(Partner $partner, string $name, ?string $phone, string $email): void
    {
        if ($partner->contacts()->exists()) {
            return;
        }

        PartnerContact::query()->create([
            'partner_id' => $partner->id,
            'name'       => $name,
            'role'       => 'Primary Contact',
            'email'      => $email,
            'phone'      => $phone,
            'is_primary' => true,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    //  External API key (FR32–33)
    // ─────────────────────────────────────────────────────────────────────────────

    private function seedExternalApiKey(Company $company, User $owner): void
    {
        $exists = ExternalApiKey::query()
            ->where('company_id', $company->id)
            ->where('name', 'SEKALORI Landing Page')
            ->whereNull('revoked_at')
            ->exists();

        if (! $exists) {
            ExternalApiKey::issueFor($company, $owner, [
                'name'           => 'SEKALORI Landing Page',
                'source_channel' => 'web',
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    //  POS: shifts + sales (FR03–08, FR31)
    // ─────────────────────────────────────────────────────────────────────────────

    /** @param array{customers: array<string, Partner>, suppliers: array<string, Partner>} $partners */
    private function seedPosData(int $cid, int $bid, int $uid, array $partners): void
    {
        $register = Register::query()
            ->where('company_id', $cid)
            ->where('code', 'GFORM-IMPORT')
            ->first();

        if ($register === null) {
            return;
        }

        // ── Shifts ────────────────────────────────────────────────────────────────

        /** @var CashierShift $pastShift */
        $pastShift = CashierShift::query()->firstOrCreate(
            ['company_id' => $cid, 'register_id' => $register->id, 'opened_at' => '2026-05-01 08:00:00'],
            [
                'branch_id'      => $bid,
                'user_id'        => $uid,
                'status'         => 'closed',
                'opened_at'      => '2026-05-01 08:00:00',
                'closed_at'      => '2026-05-31 18:00:00',
                'opening_float'  => 500000,
                'expected_cash'  => 9950000,
                'counted_cash'   => 9950000,
                'cash_variance'  => 0,
                'created_by'     => $uid,
                'updated_by'     => $uid,
            ],
        );

        /** @var CashierShift $currentShift */
        $currentShift = CashierShift::query()->firstOrCreate(
            ['company_id' => $cid, 'register_id' => $register->id, 'opened_at' => '2026-06-01 08:00:00'],
            [
                'branch_id'     => $bid,
                'user_id'       => $uid,
                'status'        => 'open',
                'opened_at'     => '2026-06-01 08:00:00',
                'opening_float' => 500000,
                'created_by'    => $uid,
                'updated_by'    => $uid,
            ],
        );

        // ── Product variants ──────────────────────────────────────────────────────

        $idn = ProductVariant::query()->where('company_id', $cid)->where('sku', 'SKL-BND-IDN')->first();
        $wst = ProductVariant::query()->where('company_id', $cid)->where('sku', 'SKL-BND-WST')->first();
        $jpn = ProductVariant::query()->where('company_id', $cid)->where('sku', 'SKL-BND-JPN')->first();
        $air = ProductVariant::query()->where('company_id', $cid)->where('sku', 'SKL-AIR-600')->first();

        $revenueAcc = Account::query()->where('company_id', $cid)->where('code', '4-1100')->first();

        // ── Sales definitions ─────────────────────────────────────────────────────
        // Each entry covers a different status/date combination so that all FR03–08
        // filter/status scenarios are represented in the UI.

        $salesData = [
            // FR08 — Completed sales (also populate income export FR31)
            [
                'number' => 'SKL-2026-0001', 'status' => 'completed',
                'partner' => $partners['customers']['CUST-001'],
                'shift'   => $pastShift,
                'variant' => $idn, 'qty' => 5,  'price' => 100000,
                'method'  => 'transfer',
                'order_date' => '2026-05-03', 'fulfil_date' => '2026-05-04',
                'completed_at' => '2026-05-04 12:00:00',
                'source' => 'import', 'notes' => 'Batch pesanan katering Mei awal',
            ],
            [
                'number' => 'SKL-2026-0002', 'status' => 'completed',
                'partner' => $partners['customers']['CUST-002'],
                'shift'   => $pastShift,
                'variant' => $wst, 'qty' => 3, 'price' => 125000,
                'method'  => 'qris',
                'order_date' => '2026-05-15', 'fulfil_date' => '2026-05-16',
                'completed_at' => '2026-05-16 12:00:00',
                'source' => 'import', 'notes' => 'Western catering batch',
            ],
            [
                'number' => 'SKL-2026-0003', 'status' => 'completed',
                'partner' => $partners['customers']['CUST-004'],
                'shift'   => $pastShift,
                'variant' => $jpn, 'qty' => 4, 'price' => 150000,
                'method'  => 'transfer',
                'order_date' => '2026-05-20', 'fulfil_date' => '2026-05-21',
                'completed_at' => '2026-05-21 12:00:00',
                'source' => 'import', 'notes' => 'Japanese catering batch',
            ],
            // FR06 — Confirmed order (FR04 can fire WhatsApp, FR08 demo = complete this)
            [
                'number' => 'SKL-2026-0004', 'status' => 'confirmed',
                'partner' => $partners['customers']['CUST-003'],
                'shift'   => $currentShift,
                'variant' => $idn, 'qty' => 2, 'price' => 100000,
                'method'  => 'transfer',
                'order_date' => '2026-06-05', 'fulfil_date' => '2026-06-06',
                'completed_at' => null,
                'source' => 'import', 'notes' => 'Juni awal batch Ahmad',
            ],
            // Another confirmed order (filter demo)
            [
                'number' => 'SKL-2026-0005', 'status' => 'confirmed',
                'partner' => $partners['customers']['CUST-001'],
                'shift'   => $currentShift,
                'variant' => $jpn, 'qty' => 3, 'price' => 150000,
                'method'  => 'transfer',
                'order_date' => '2026-06-05', 'fulfil_date' => '2026-06-07',
                'completed_at' => null,
                'source' => 'import', 'notes' => 'Japanese Juni batch Budi',
            ],
            // FR03 — Draft (walk-in, no partner)
            [
                'number' => 'SKL-2026-0006', 'status' => 'draft',
                'partner' => null,
                'shift'   => $currentShift,
                'variant' => $air, 'qty' => 10, 'price' => 5000,
                'method'  => null,
                'order_date' => '2026-06-06', 'fulfil_date' => '2026-06-06',
                'completed_at' => null,
                'source' => 'pos', 'notes' => 'Walk-in — sedang diproses',
            ],
            // FR05 — Cancelled order
            [
                'number' => 'SKL-2026-0007', 'status' => 'cancelled',
                'partner' => $partners['customers']['CUST-002'],
                'shift'   => $pastShift,
                'variant' => $wst, 'qty' => 2, 'price' => 125000,
                'method'  => null,
                'order_date' => '2026-05-10', 'fulfil_date' => '2026-05-11',
                'completed_at' => null,
                'source' => 'import', 'notes' => 'Dibatalkan pelanggan sebelum dikonfirmasi',
            ],
            // FR07 — Another completed on a different date for date-filter demo
            [
                'number' => 'SKL-2026-0008', 'status' => 'completed',
                'partner' => $partners['customers']['CUST-003'],
                'shift'   => $currentShift,
                'variant' => $idn, 'qty' => 6, 'price' => 100000,
                'method'  => 'qris',
                'order_date' => '2026-06-02', 'fulfil_date' => '2026-06-03',
                'completed_at' => '2026-06-03 13:00:00',
                'source' => 'import', 'notes' => 'Indonesian Local Juni awal',
            ],
        ];

        foreach ($salesData as $def) {
            $exists = Sale::query()
                ->where('company_id', $cid)
                ->where('sale_number', $def['number'])
                ->exists();

            if ($exists) {
                continue;
            }

            $total  = $def['qty'] * $def['price'];
            $paid   = in_array($def['status'], ['completed', 'confirmed']) ? $total : 0;

            $sale = Sale::query()->create([
                'company_id'       => $cid,
                'branch_id'        => $bid,
                'register_id'      => $register->id,
                'cashier_shift_id' => $def['shift']->id,
                'sale_number'      => $def['number'],
                'type'             => 'catering',
                'partner_id'       => $def['partner']?->id,
                'customer_name'    => $def['partner']?->name ?? 'Walk-in Customer',
                'status'           => $def['status'],
                'source'           => $def['source'],
                'source_channel'   => $def['source'] === 'import' ? 'gform' : $def['source'],
                'order_date'       => $def['order_date'],
                'fulfilment_date'  => $def['fulfil_date'],
                'currency_id'      => 1,
                'exchange_rate'    => 1,
                'subtotal'         => $total,
                'discount_total'   => 0,
                'tax_total'        => 0,
                'total'            => $total,
                'amount_paid'      => $paid,
                'notes'            => $def['notes'],
                'completed_at'     => $def['completed_at'],
                'created_by'       => $uid,
                'updated_by'       => $uid,
            ]);

            if ($def['variant'] !== null) {
                SaleLine::query()->create([
                    'sale_id'            => $sale->id,
                    'product_variant_id' => $def['variant']->id,
                    'description'        => $def['variant']->name,
                    'quantity'           => $def['qty'],
                    'unit_price'         => $def['price'],
                    'discount'           => 0,
                    'line_subtotal'      => $total,
                    'tax_amount'         => 0,
                    'line_total'         => $total,
                    'revenue_account_id' => $revenueAcc?->id,
                    'is_giveaway'        => false,
                ]);
            }

            if ($def['method'] !== null && $paid > 0) {
                SalePayment::query()->create([
                    'sale_id'    => $sale->id,
                    'method'     => $def['method'],
                    'amount'     => $total,
                    'paid_at'    => $def['completed_at'] ?? ($def['order_date'].' 12:00:00'),
                    'created_by' => $uid,
                ]);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    //  Finance invoices / AR (FR10–13, FR29–31)
    // ─────────────────────────────────────────────────────────────────────────────

    /** @param array{customers: array<string, Partner>, suppliers: array<string, Partner>} $partners */
    private function seedInvoices(int $cid, int $bid, int $uid, array $partners): void
    {
        $revenueAcc = Account::query()->where('company_id', $cid)->where('code', '4-1100')->first();

        [$budi, $siti, $ahmad, $dewi] = [
            $partners['customers']['CUST-001'],
            $partners['customers']['CUST-002'],
            $partners['customers']['CUST-003'],
            $partners['customers']['CUST-004'],
        ];

        $defs = [
            // FR13 — filter by date across two periods
            ['number' => 'INV-2026-001', 'partner' => $budi,  'date' => '2026-05-02', 'due' => '2026-06-01', 'status' => 'posted', 'amount' => 3500000, 'desc' => 'Catering Indonesian Local 35 pax — Mei'],
            ['number' => 'INV-2026-002', 'partner' => $siti,  'date' => '2026-05-16', 'due' => '2026-06-15', 'status' => 'posted', 'amount' => 1750000, 'desc' => 'Catering Western Bundle 14 pax — Mei'],
            ['number' => 'INV-2026-003', 'partner' => $dewi,  'date' => '2026-05-21', 'due' => '2026-06-20', 'status' => 'posted', 'amount' => 2700000, 'desc' => 'Catering Japanese Bundle 18 pax — Mei'],
            ['number' => 'INV-2026-004', 'partner' => $ahmad, 'date' => '2026-06-01', 'due' => '2026-07-01', 'status' => 'posted', 'amount' => 2100000, 'desc' => 'Catering Indonesian Local 21 pax — Juni'],
            ['number' => 'INV-2026-005', 'partner' => $budi,  'date' => '2026-06-04', 'due' => '2026-07-04', 'status' => 'posted', 'amount' => 1500000, 'desc' => 'Catering Western Bundle 12 pax — Juni'],
            // FR10-11 — draft + voided for status variety
            ['number' => 'INV-2026-006', 'partner' => $dewi,  'date' => '2026-06-06', 'due' => '2026-07-06', 'status' => 'draft',  'amount' => 900000,  'desc' => 'Catering Indonesian Local 9 pax — draft'],
            ['number' => 'INV-2026-007', 'partner' => $siti,  'date' => '2026-04-30', 'due' => '2026-05-30', 'status' => 'voided', 'amount' => 875000,  'desc' => 'Nasi Box Regular duplikat — dibatalkan'],
        ];

        foreach ($defs as $def) {
            if (Invoice::query()->where('company_id', $cid)->where('invoice_number', $def['number'])->exists()) {
                continue;
            }

            $invoice = Invoice::query()->create([
                'company_id'     => $cid,
                'branch_id'      => $bid,
                'invoice_number' => $def['number'],
                'partner_id'     => $def['partner']->id,
                'currency_id'    => 1,
                'exchange_rate'  => 1,
                'invoice_date'   => $def['date'],
                'due_date'       => $def['due'],
                'status'         => $def['status'],
                'subtotal'       => $def['amount'],
                'discount_total' => 0,
                'tax_total'      => 0,
                'total'          => $def['amount'],
                'amount_paid'    => $def['status'] === 'posted' ? $def['amount'] : 0,
                'notes'          => $def['desc'],
                'created_by'     => $uid,
                'updated_by'     => $uid,
            ]);

            InvoiceLine::query()->create([
                'invoice_id'        => $invoice->id,
                'description'       => $def['desc'],
                'revenue_account_id' => $revenueAcc?->id,
                'quantity'          => 1,
                'unit_price'        => $def['amount'],
                'discount'          => 0,
                'line_subtotal'     => $def['amount'],
                'tax_amount'        => 0,
                'line_total'        => $def['amount'],
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    //  Finance bills / AP (FR14–17, FR20–22)
    // ─────────────────────────────────────────────────────────────────────────────

    /** @param array{customers: array<string, Partner>, suppliers: array<string, Partner>} $partners */
    private function seedBills(int $cid, int $bid, int $uid, array $partners): void
    {
        $expenseAcc = Account::query()->where('company_id', $cid)->where('code', '5-1100')->first();

        [$bumbu, $fresh] = [
            $partners['suppliers']['SUPP-001'],
            $partners['suppliers']['SUPP-002'],
        ];

        $defs = [
            // FR20 — filter by date; FR21 — filter by account
            ['number' => 'BILL-2026-001', 'partner' => $bumbu, 'date' => '2026-05-05', 'due' => '2026-06-04', 'status' => 'posted', 'amount' => 1250000, 'desc' => 'Rempah & bumbu dapur — batch Mei'],
            ['number' => 'BILL-2026-002', 'partner' => $fresh, 'date' => '2026-05-20', 'due' => '2026-06-19', 'status' => 'posted', 'amount' => 980000,  'desc' => 'Sayur segar & protein — batch Mei'],
            ['number' => 'BILL-2026-003', 'partner' => $bumbu, 'date' => '2026-05-28', 'due' => '2026-06-27', 'status' => 'posted', 'amount' => 680000,  'desc' => 'Santan & kelapa segar — Mei akhir'],
            ['number' => 'BILL-2026-004', 'partner' => $bumbu, 'date' => '2026-06-03', 'due' => '2026-07-02', 'status' => 'posted', 'amount' => 1100000, 'desc' => 'Rempah & bumbu dapur — batch Juni'],
            ['number' => 'BILL-2026-005', 'partner' => $fresh, 'date' => '2026-06-04', 'due' => '2026-07-03', 'status' => 'posted', 'amount' => 850000,  'desc' => 'Sayur segar & protein — batch Juni'],
            // FR14-15 — draft bill (waiting approval)
            ['number' => 'BILL-2026-006', 'partner' => $fresh, 'date' => '2026-06-06', 'due' => '2026-07-06', 'status' => 'draft',  'amount' => 620000,  'desc' => 'Buah & pelengkap — Juni menunggu persetujuan'],
            // FR17 — voided bill (delete expense)
            ['number' => 'BILL-2026-007', 'partner' => $bumbu, 'date' => '2026-04-28', 'due' => '2026-05-28', 'status' => 'voided', 'amount' => 500000,  'desc' => 'Pesanan April duplikat — dibatalkan'],
        ];

        foreach ($defs as $def) {
            if (Bill::query()->where('company_id', $cid)->where('bill_number', $def['number'])->exists()) {
                continue;
            }

            $bill = Bill::query()->create([
                'company_id'       => $cid,
                'branch_id'        => $bid,
                'bill_number'      => $def['number'],
                'partner_id'       => $def['partner']->id,
                'currency_id'      => 1,
                'exchange_rate'    => 1,
                'bill_date'        => $def['date'],
                'due_date'         => $def['due'],
                'status'           => $def['status'],
                'subtotal'         => $def['amount'],
                'discount_total'   => 0,
                'tax_total'        => 0,
                'withholding_total' => 0,
                'total'            => $def['amount'],
                'amount_paid'      => $def['status'] === 'posted' ? $def['amount'] : 0,
                'notes'            => $def['desc'],
                'created_by'       => $uid,
                'updated_by'       => $uid,
            ]);

            BillLine::query()->create([
                'bill_id'           => $bill->id,
                'description'       => $def['desc'],
                'expense_account_id' => $expenseAcc?->id,
                'quantity'          => 1,
                'unit_price'        => $def['amount'],
                'discount'          => 0,
                'line_subtotal'     => $def['amount'],
                'tax_amount'        => 0,
                'withholding_amount' => 0,
                'line_total'        => $def['amount'],
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    //  Finance payments (FR22 — outbound expense export, FR31 — inbound income)
    // ─────────────────────────────────────────────────────────────────────────────

    /** @param array{customers: array<string, Partner>, suppliers: array<string, Partner>} $partners */
    private function seedPayments(int $cid, int $bid, int $uid, array $partners): void
    {
        $cashAcc = Account::query()->where('company_id', $cid)->where('code', '1-1100')->first();

        [$budi, $siti, $ahmad, $dewi] = [
            $partners['customers']['CUST-001'],
            $partners['customers']['CUST-002'],
            $partners['customers']['CUST-003'],
            $partners['customers']['CUST-004'],
        ];
        [$bumbu, $fresh] = [
            $partners['suppliers']['SUPP-001'],
            $partners['suppliers']['SUPP-002'],
        ];

        $defs = [
            // ── Inbound — customer receipts (FR31 income export) ──────────────────
            ['number' => 'PAY-IN-2026-001', 'partner' => $budi,  'type' => 'inbound',  'date' => '2026-05-03', 'method' => 'transfer', 'amount' => 3500000, 'notes' => 'Pelunasan INV-2026-001'],
            ['number' => 'PAY-IN-2026-002', 'partner' => $siti,  'type' => 'inbound',  'date' => '2026-05-17', 'method' => 'qris',     'amount' => 1750000, 'notes' => 'Pelunasan INV-2026-002'],
            ['number' => 'PAY-IN-2026-003', 'partner' => $dewi,  'type' => 'inbound',  'date' => '2026-05-22', 'method' => 'transfer', 'amount' => 2700000, 'notes' => 'Pelunasan INV-2026-003'],
            ['number' => 'PAY-IN-2026-004', 'partner' => $ahmad, 'type' => 'inbound',  'date' => '2026-06-02', 'method' => 'transfer', 'amount' => 2100000, 'notes' => 'Pelunasan INV-2026-004'],
            ['number' => 'PAY-IN-2026-005', 'partner' => $budi,  'type' => 'inbound',  'date' => '2026-06-05', 'method' => 'cash',     'amount' => 1500000, 'notes' => 'Pelunasan INV-2026-005'],
            // ── Outbound — supplier payments (FR22 expense export) ────────────────
            ['number' => 'PAY-OUT-2026-001', 'partner' => $bumbu, 'type' => 'outbound', 'date' => '2026-05-07', 'method' => 'transfer', 'amount' => 1250000, 'notes' => 'Pelunasan BILL-2026-001'],
            ['number' => 'PAY-OUT-2026-002', 'partner' => $fresh, 'type' => 'outbound', 'date' => '2026-05-22', 'method' => 'transfer', 'amount' => 980000,  'notes' => 'Pelunasan BILL-2026-002'],
            ['number' => 'PAY-OUT-2026-003', 'partner' => $bumbu, 'type' => 'outbound', 'date' => '2026-05-30', 'method' => 'transfer', 'amount' => 680000,  'notes' => 'Pelunasan BILL-2026-003'],
            ['number' => 'PAY-OUT-2026-004', 'partner' => $bumbu, 'type' => 'outbound', 'date' => '2026-06-05', 'method' => 'transfer', 'amount' => 1100000, 'notes' => 'Pelunasan BILL-2026-004'],
            ['number' => 'PAY-OUT-2026-005', 'partner' => $fresh, 'type' => 'outbound', 'date' => '2026-06-05', 'method' => 'transfer', 'amount' => 850000,  'notes' => 'Pelunasan BILL-2026-005'],
        ];

        foreach ($defs as $def) {
            if (Payment::query()->where('company_id', $cid)->where('payment_number', $def['number'])->exists()) {
                continue;
            }

            Payment::query()->create([
                'company_id'     => $cid,
                'branch_id'      => $bid,
                'payment_number' => $def['number'],
                'partner_id'     => $def['partner']->id,
                'payment_type'   => $def['type'],
                'payment_date'   => $def['date'],
                'payment_method' => $def['method'],
                'amount'         => $def['amount'],
                'currency_id'    => 1,
                'exchange_rate'  => 1,
                'cash_account_id' => $cashAcc?->id,
                'status'         => 'posted',
                'notes'          => $def['notes'],
                'created_by'     => $uid,
                'updated_by'     => $uid,
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    //  Journal entries (FR09, FR29–30 — income statement / P&L report)
    // ─────────────────────────────────────────────────────────────────────────────

    private function seedJournalEntries(int $cid, int $bid, int $uid): void
    {
        $cashAcc    = Account::query()->where('company_id', $cid)->where('code', '1-1100')->first();
        $revenueAcc = Account::query()->where('company_id', $cid)->where('code', '4-1100')->first();
        $expenseAcc = Account::query()->where('company_id', $cid)->where('code', '5-1100')->first();
        $cogsAcc    = Account::query()->where('company_id', $cid)->where('code', '5-1200')->first();
        $apAcc      = Account::query()->where('company_id', $cid)->where('code', '2-1100')->first();

        if (! $cashAcc || ! $revenueAcc || ! $expenseAcc) {
            return;
        }

        $periodMay  = AccountingPeriod::query()->where('company_id', $cid)->where('name', 'May 2026')->first();
        $periodJune = AccountingPeriod::query()->where('company_id', $cid)->where('name', 'June 2026')->first();

        // Each entry is a balanced double-entry pair (total debit == total credit).
        $entries = [
            // ── May 2026 ─────────────────────────────────────────────────────────
            [
                'number' => 'JNL-2026-0001', 'date' => '2026-05-31', 'period' => $periodMay,
                'desc'   => 'Mei 2026 — Pendapatan catering (Indonesian + Western + Japanese)',
                'lines'  => [
                    [$cashAcc,    8950000, 0,       'Kas diterima dari pesanan catering Mei'],
                    [$revenueAcc, 0,       8950000, 'Pendapatan penjualan catering Mei'],
                ],
            ],
            [
                'number' => 'JNL-2026-0002', 'date' => '2026-05-31', 'period' => $periodMay,
                'desc'   => 'Mei 2026 — HPP dan biaya bahan baku',
                'lines'  => [
                    [$cogsAcc ?? $expenseAcc, 2910000, 0,       'HPP bahan baku katering Mei'],
                    [$cashAcc,                0,       2910000, 'Kas keluar untuk bahan baku Mei'],
                ],
            ],
            [
                'number' => 'JNL-2026-0003', 'date' => '2026-05-31', 'period' => $periodMay,
                'desc'   => 'Mei 2026 — Biaya operasional (bumbu, utilities)',
                'lines'  => [
                    [$expenseAcc, 1400000, 0,       'Biaya bumbu dan rempah Mei'],
                    [$cashAcc,    0,       1400000, 'Kas keluar operasional Mei'],
                ],
            ],
            // ── June 2026 ────────────────────────────────────────────────────────
            [
                'number' => 'JNL-2026-0004', 'date' => '2026-06-05', 'period' => $periodJune,
                'desc'   => 'Juni 2026 — Pendapatan catering (Indonesian + batch Ahmad)',
                'lines'  => [
                    [$cashAcc,    3600000, 0,       'Kas diterima pesanan katering Juni'],
                    [$revenueAcc, 0,       3600000, 'Pendapatan penjualan katering Juni'],
                ],
            ],
            [
                'number' => 'JNL-2026-0005', 'date' => '2026-06-05', 'period' => $periodJune,
                'desc'   => 'Juni 2026 — HPP dan biaya bahan baku',
                'lines'  => [
                    [$cogsAcc ?? $expenseAcc, 1950000, 0,       'HPP bahan baku katering Juni'],
                    [$cashAcc,                0,       1950000, 'Kas keluar untuk bahan baku Juni'],
                ],
            ],
            [
                'number' => 'JNL-2026-0006', 'date' => '2026-06-05', 'period' => $periodJune,
                'desc'   => 'Juni 2026 — Biaya operasional',
                'lines'  => [
                    [$expenseAcc, 750000, 0,      'Biaya operasional Juni'],
                    [$cashAcc,    0,      750000, 'Kas keluar operasional Juni'],
                ],
            ],
        ];

        foreach ($entries as $def) {
            if (JournalEntry::query()->where('company_id', $cid)->where('entry_number', $def['number'])->exists()) {
                continue;
            }

            $entry = JournalEntry::query()->create([
                'company_id'          => $cid,
                'branch_id'           => $bid,
                'entry_number'        => $def['number'],
                'entry_date'          => $def['date'],
                'accounting_period_id' => $def['period']?->id,
                'description'         => $def['desc'],
                'currency_id'         => 1,
                'exchange_rate'       => 1,
                'status'              => 'posted',
                'posted_at'           => now(),
                'posted_by'           => $uid,
                'created_by'          => $uid,
                'updated_by'          => $uid,
            ]);

            foreach ($def['lines'] as [$account, $debit, $credit, $desc]) {
                if ($account === null) {
                    continue;
                }

                JournalLine::query()->create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $account->id,
                    'description'      => $desc,
                    'debit'            => $debit,
                    'credit'           => $credit,
                ]);
            }
        }
    }
}
