<?php

use App\Models\User;
use App\Modules\Finance\Exports\ExpenseExport;
use App\Modules\Finance\Exports\IncomeExport;
use App\Modules\Finance\Models\Payment;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\Membership;
use App\Modules\Partners\Models\Partner;
use App\Modules\Pos\Models\Sale;
use Database\Seeders\CurrencySeeder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
    $this->seed(CurrencySeeder::class);
});

/**
 * Download an export over HTTP and read the real workbook back, so these tests
 * pin the produced file rather than the library that produced it.
 */
function downloadedSheet(string $token, int $companyId, string $kind): Worksheet
{
    $response = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
        ->get("/api/v1/finance/exports/{$kind}.xlsx")
        ->assertOk();

    $path = tempnam(sys_get_temp_dir(), 'export').'.xlsx';
    file_put_contents($path, $response->streamedContent());

    try {
        return IOFactory::load($path)->getActiveSheet();
    } finally {
        @unlink($path);
    }
}

function makePayment(int $companyId, int $cashAccountId, string $type, string $amount, string $status = 'posted'): Payment
{
    $partner = Partner::create([
        'company_id' => $companyId,
        'type' => $type === 'inbound' ? 'customer' : 'vendor',
        'name' => $type === 'inbound' ? 'Acme Corp' : 'Supplier Inc',
        'code' => 'PRT-'.uniqid(),
        'status' => 'active',
    ]);

    return Payment::create([
        'company_id' => $companyId,
        'partner_id' => $partner->id,
        'payment_number' => 'PAY-'.uniqid(),
        'payment_type' => $type,
        'payment_date' => '2026-06-10',
        'payment_method' => 'cash',
        'amount' => $amount,
        'currency_id' => 1,
        'exchange_rate' => '1.00000000',
        'cash_account_id' => $cashAccountId,
        'status' => $status,
    ]);
}

function makeCompletedSale(int $companyId, int $branchId, string $total): Sale
{
    return Sale::create([
        'company_id' => $companyId,
        'branch_id' => $branchId,
        'sale_number' => 'POS-'.uniqid(),
        'type' => 'pos',
        'customer_name' => 'Walk-in',
        'status' => 'completed',
        'order_date' => '2026-06-12',
        'completed_at' => '2026-06-12 10:00:00',
        'currency_id' => 1,
        'exchange_rate' => '1.00000000',
        'total' => $total,
    ]);
}

describe('Income export', function () {
    it('unions posted inbound payments with completed POS sales', function (): void {
        [, $token, $companyId, $branchId] = financeActor();
        $cash = createAccount($token, $companyId, ['code' => '1-1010', 'name' => 'Cash', 'type' => 'asset']);

        makePayment($companyId, $cash, 'inbound', '100000.0000');
        makePayment($companyId, $cash, 'outbound', '50000.0000'); // must NOT leak in
        makeCompletedSale($companyId, $branchId, '250000.0000');

        $rows = (new IncomeExport($companyId))->collection();

        expect($rows)->toHaveCount(2);
        expect($rows->sum('amount'))->toBe(350000.0);
        expect($rows->pluck('source')->sort()->values()->all())->toBe(['POS Sale', 'Payment']);
    });

    it('streams a valid xlsx download', function (): void {
        [, $token, $companyId] = financeActor();
        createAccount($token, $companyId, ['code' => '1-1010', 'name' => 'Cash', 'type' => 'asset']);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->get('/api/v1/finance/exports/income.xlsx')
            ->assertOk()
            ->assertHeader(
                'content-type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            );
    });

    it('streams a workbook whose sheet, headings and rows match the export', function (): void {
        [, $token, $companyId, $branchId] = financeActor();
        $cash = createAccount($token, $companyId, ['code' => '1-1010', 'name' => 'Cash', 'type' => 'asset']);

        makePayment($companyId, $cash, 'inbound', '100000.0000');
        makeCompletedSale($companyId, $branchId, '250000.0000');

        $sheet = downloadedSheet($token, $companyId, 'income');

        expect($sheet->getTitle())->toBe('Income')
            ->and($sheet->rangeToArray('A1:F1', null, true, false)[0])
            ->toBe(['Date', 'Source', 'Reference', 'Party', 'Method', 'Amount'])
            ->and($sheet->getStyle('A1')->getFont()->getBold())->toBeTrue();

        // Two income rows land under the header, and amounts stay numeric.
        $rows = $sheet->toArray(null, true, false, false);
        expect($rows)->toHaveCount(3);
        expect(array_column(array_slice($rows, 1), 1))->toEqualCanonicalizing(['Payment', 'POS Sale']);
        expect(array_sum(array_column(array_slice($rows, 1), 5)))->toBe(350000.0);
    });

    it('streams an expense workbook with its own sheet name and headings', function (): void {
        [, $token, $companyId] = financeActor();
        $cash = createAccount($token, $companyId, ['code' => '1-1010', 'name' => 'Cash', 'type' => 'asset']);
        makePayment($companyId, $cash, 'outbound', '70000.0000');

        $sheet = downloadedSheet($token, $companyId, 'expense');

        expect($sheet->getTitle())->toBe('Expense')
            ->and($sheet->rangeToArray('A1:E1', null, true, false)[0])
            ->toBe(['Date', 'Reference', 'Supplier', 'Method', 'Amount'])
            ->and($sheet->getStyle('A1')->getFont()->getBold())->toBeTrue();

        $rows = $sheet->toArray(null, true, false, false);
        expect($rows)->toHaveCount(2)
            ->and($rows[1][4])->toBe(70000.0);
    });

    it('rejects non-xlsx formats via the route constraint', function (): void {
        [, $token, $companyId] = financeActor();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->get('/api/v1/finance/exports/income.csv')
            ->assertNotFound();
    });

    it('requires authentication', function (): void {
        $this->getJson('/api/v1/finance/exports/income.xlsx')->assertUnauthorized();
    });

    it('forbids exporting financial data without the finance.view permission', function (): void {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Cashier Co', 'slug' => 'cashier-co-export', 'status' => 'active']);
        Membership::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'member',
            'status' => 'active',
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.access_token');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $company->id)
            ->get('/api/v1/finance/exports/income.xlsx')
            ->assertForbidden();
    });
});

describe('Expense export', function () {
    it('returns only posted outbound payments, scoped to the company', function (): void {
        // Create both actors up front: company-scoped writes mutate the Spatie
        // permission team context, which would otherwise break a later actor.
        [, $tokenA, $companyA] = financeActor();
        [, $tokenB, $companyB] = financeActor();

        $cashA = createAccount($tokenA, $companyA, ['code' => '1-1010', 'name' => 'Cash', 'type' => 'asset']);
        $cashB = createAccount($tokenB, $companyB, ['code' => '1-1010', 'name' => 'Cash', 'type' => 'asset']);

        makePayment($companyA, $cashA, 'outbound', '70000.0000');
        makePayment($companyA, $cashA, 'outbound', '30000.0000');
        makePayment($companyA, $cashA, 'inbound', '999999.0000'); // must NOT leak in
        makePayment($companyB, $cashB, 'outbound', '11111.0000'); // other company

        $rows = (new ExpenseExport($companyA))->collection();

        expect($rows)->toHaveCount(2);
        expect($rows->sum('amount'))->toBe(100000.0);
    });
});
