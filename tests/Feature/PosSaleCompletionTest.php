<?php

use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Models\TaxRate;
use App\Modules\Inventory\Models\StockLot;
use App\Modules\Partners\Models\Partner;
use Database\Seeders\CurrencySeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
    $this->seed(CurrencySeeder::class);
});

function posCompletionVariant(string $token, int $companyId, string $sku, int $price): int
{
    $uom = createUnit($token, $companyId, strtolower($sku).'-pcs');
    $productId = createProduct($token, $companyId, [
        'name' => 'Completion Product '.$sku,
        'base_uom_id' => $uom,
        'variants' => [['sku' => $sku]],
    ]);

    $variantId = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
        ->getJson("/api/v1/inventory/products/{$productId}")
        ->json('data.product.variants.0.id');

    $priceListId = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
        ->postJson('/api/v1/inventory/price-lists', [
            'name' => 'Completion Retail '.$sku,
            'is_default' => true,
        ])
        ->assertCreated()
        ->json('data.price_list.id');

    test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
        ->putJson("/api/v1/inventory/price-lists/{$priceListId}/prices", [
            'product_variant_id' => $variantId,
            'price' => $price,
            'effective_from' => '2026-01-01',
        ])
        ->assertSuccessful();

    return $variantId;
}

function posCompletionRegister(string $token, int $companyId, int $branchId, int $cashAccountId): int
{
    return test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
        ->postJson('/api/v1/pos/registers', [
            'name' => 'Completion Register '.uniqid(),
            'code' => 'COMP-'.uniqid(),
            'branch_id' => $branchId,
            'cash_account_id' => $cashAccountId,
        ])
        ->assertCreated()
        ->json('data.register.id');
}

function posPostingAccounts(string $token, int $companyId): array
{
    $cash = createAccount($token, $companyId, ['code' => '1-1100', 'name' => 'Cash', 'type' => 'asset']);
    $ar = createAccount($token, $companyId, ['code' => '1-1200', 'name' => 'Accounts Receivable', 'type' => 'asset']);
    $inventory = createAccount($token, $companyId, ['code' => '1-1300', 'name' => 'Inventory Asset', 'type' => 'asset']);
    $vat = createAccount($token, $companyId, ['code' => '2-1200', 'name' => 'VAT Output', 'type' => 'liability']);
    $revenue = createAccount($token, $companyId, ['code' => '4-1100', 'name' => 'Sales Revenue', 'type' => 'revenue']);
    $cogs = createAccount($token, $companyId, ['code' => '5-1200', 'name' => 'COGS', 'type' => 'expense']);

    test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
        ->putJson('/api/v1/finance/account-mappings', [
            'mappings' => [
                ['key' => 'accounts_receivable', 'account_id' => $ar],
                ['key' => 'sales_revenue', 'account_id' => $revenue],
                ['key' => 'vat_output', 'account_id' => $vat],
                ['key' => 'cogs', 'account_id' => $cogs],
                ['key' => 'inventory_asset', 'account_id' => $inventory],
            ],
        ])
        ->assertSuccessful();

    test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
        ->postJson('/api/v1/finance/periods', [
            'name' => 'May 2026',
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
        ])
        ->assertCreated();

    return compact('cash', 'ar', 'inventory', 'vat', 'revenue', 'cogs');
}

function posStockReceipt(string $token, int $companyId, int $branchId, int $variantId, int $quantity, int $unitCost): void
{
    test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
        ->withHeader('X-Branch-Id', (string) $branchId)
        ->postJson('/api/v1/inventory/stock/receipts', [
            'product_variant_id' => $variantId,
            'branch_id' => $branchId,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'received_at' => '2026-05-01',
        ])
        ->assertCreated();
}

describe('POS sale completion', function () {
    it('completes a paid counter sale by issuing stock and posting revenue and COGS journals', function (): void {
        [, $token, $companyId, $branchId] = financeActor();
        $accounts = posPostingAccounts($token, $companyId);
        $registerId = posCompletionRegister($token, $companyId, $branchId, $accounts['cash']);
        $variantId = posCompletionVariant($token, $companyId, 'POS-COMP-1', 50000);
        posStockReceipt($token, $companyId, $branchId, $variantId, 10, 30000);
        $taxRate = TaxRate::create([
            'company_id' => $companyId,
            'name' => 'VAT 11%',
            'type' => 'vat',
            'rate' => 11.0000,
            'is_active' => true,
        ]);

        $saleId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/pos/sales', [
                'type' => 'counter',
                'branch_id' => $branchId,
                'register_id' => $registerId,
                'order_date' => '2026-05-22',
                'lines' => [[
                    'product_variant_id' => $variantId,
                    'quantity' => 2,
                    'tax_rate_id' => $taxRate->id,
                ]],
            ])
            ->assertCreated()
            ->json('data.sale.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/sales/{$saleId}/payments", [
                'method' => 'cash',
                'amount' => 111000,
            ])
            ->assertCreated();

        $response = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/sales/{$saleId}/complete")
            ->assertSuccessful()
            ->assertJsonPath('data.sale.status', 'completed');

        $revenueEntryId = $response->json('data.sale.revenue_journal_entry_id');
        $cogsEntryId = $response->json('data.sale.cogs_journal_entry_id');
        expect($revenueEntryId)->not->toBeNull()
            ->and($cogsEntryId)->not->toBeNull();

        expect((float) StockLot::query()->where('product_variant_id', $variantId)->sum('remaining_quantity'))->toBe(8.0);

        $revenueLines = JournalEntry::findOrFail($revenueEntryId)->lines()->get();
        expect((float) $revenueLines->firstWhere('account_id', $accounts['cash'])->debit)->toBe(111000.0)
            ->and((float) $revenueLines->firstWhere('account_id', $accounts['revenue'])->credit)->toBe(100000.0)
            ->and((float) $revenueLines->firstWhere('account_id', $accounts['vat'])->credit)->toBe(11000.0);

        $cogsLines = JournalEntry::findOrFail($cogsEntryId)->lines()->get();
        expect((float) $cogsLines->firstWhere('account_id', $accounts['cogs'])->debit)->toBe(60000.0)
            ->and((float) $cogsLines->firstWhere('account_id', $accounts['inventory'])->credit)->toBe(60000.0);
    });

    it('fails completion when stock is insufficient and leaves the sale draft', function (): void {
        [, $token, $companyId, $branchId] = financeActor();
        $accounts = posPostingAccounts($token, $companyId);
        $registerId = posCompletionRegister($token, $companyId, $branchId, $accounts['cash']);
        $variantId = posCompletionVariant($token, $companyId, 'POS-COMP-2', 50000);
        posStockReceipt($token, $companyId, $branchId, $variantId, 3, 30000);

        $saleId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/pos/sales', [
                'type' => 'counter',
                'branch_id' => $branchId,
                'register_id' => $registerId,
                'order_date' => '2026-05-22',
                'lines' => [['product_variant_id' => $variantId, 'quantity' => 5]],
            ])
            ->assertCreated()
            ->json('data.sale.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/sales/{$saleId}/payments", [
                'method' => 'cash',
                'amount' => 250000,
            ])
            ->assertCreated();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/sales/{$saleId}/complete")
            ->assertUnprocessable();

        $this->assertDatabaseHas('sales', ['id' => $saleId, 'status' => 'draft']);
        expect(JournalEntry::query()->where('reference_id', $saleId)->count())->toBe(0);
    });

    it('debits accounts receivable for an unpaid catering balance', function (): void {
        [, $token, $companyId, $branchId] = financeActor();
        $accounts = posPostingAccounts($token, $companyId);
        $registerId = posCompletionRegister($token, $companyId, $branchId, $accounts['cash']);
        $variantId = posCompletionVariant($token, $companyId, 'POS-COMP-3', 100000);
        posStockReceipt($token, $companyId, $branchId, $variantId, 2, 40000);
        $partner = Partner::create([
            'company_id' => $companyId,
            'type' => 'customer',
            'name' => 'Catering Customer',
            'code' => 'CAT-001',
            'status' => 'active',
        ]);

        $saleId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/pos/sales', [
                'type' => 'catering',
                'branch_id' => $branchId,
                'register_id' => $registerId,
                'partner_id' => $partner->id,
                'order_date' => '2026-05-22',
                'fulfilment_date' => '2026-05-25',
                'lines' => [['product_variant_id' => $variantId, 'quantity' => 1]],
            ])
            ->assertCreated()
            ->json('data.sale.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/sales/{$saleId}/confirm")
            ->assertSuccessful();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/sales/{$saleId}/complete")
            ->assertSuccessful()
            ->assertJsonPath('data.sale.status', 'completed');

        $sale = JournalEntry::query()->where('reference_id', $saleId)->where('entry_number', 'like', 'JE-POS-REV-%')->firstOrFail();
        $arLine = $sale->lines()->where('account_id', $accounts['ar'])->firstOrFail();
        expect((float) $arLine->debit)->toBe(100000.0);
    });
});
