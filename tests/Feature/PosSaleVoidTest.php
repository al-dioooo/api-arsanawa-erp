<?php

use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Inventory\Models\StockLot;
use App\Modules\Partners\Models\Partner;
use Database\Seeders\CurrencySeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
    $this->seed(CurrencySeeder::class);
});

function posVoidVariant(string $token, int $companyId, string $sku, int $price): int
{
    $uom = createUnit($token, $companyId, strtolower($sku).'-pcs');
    $productId = createProduct($token, $companyId, [
        'name' => 'Void Product '.$sku,
        'base_uom_id' => $uom,
        'variants' => [['sku' => $sku]],
    ]);

    $variantId = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
        ->getJson("/api/v1/inventory/products/{$productId}")
        ->json('data.product.variants.0.id');

    $priceListId = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
        ->postJson('/api/v1/inventory/price-lists', [
            'name' => 'Void Retail '.$sku,
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

function posVoidRegister(string $token, int $companyId, int $branchId, ?int $cashAccountId = null): int
{
    return test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
        ->postJson('/api/v1/pos/registers', [
            'name' => 'Void Register '.uniqid(),
            'code' => 'VOID-'.uniqid(),
            'branch_id' => $branchId,
            'cash_account_id' => $cashAccountId,
        ])
        ->assertCreated()
        ->json('data.register.id');
}

function posVoidPostingAccounts(string $token, int $companyId): array
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

function posVoidStockReceipt(string $token, int $companyId, int $branchId, int $variantId, int $quantity, int $unitCost): void
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

describe('POS sale voiding', function () {
    it('cancels draft and confirmed sales without reversing stock or journals', function (): void {
        [, $token, $companyId, $branchId] = financeActor();
        $cashAccountId = createAccount($token, $companyId, [
            'code' => '1-1100',
            'name' => 'Register Cash',
            'type' => 'asset',
        ]);
        $registerId = posVoidRegister($token, $companyId, $branchId, $cashAccountId);
        $counterVariantId = posVoidVariant($token, $companyId, 'POS-CANCEL-1', 50000);
        $cateringVariantId = posVoidVariant($token, $companyId, 'POS-CANCEL-2', 75000);
        $partner = Partner::create([
            'company_id' => $companyId,
            'type' => 'customer',
            'name' => 'Cancel Catering Customer',
            'code' => 'CANCEL-CUST-001',
            'status' => 'active',
        ]);

        $draftSaleId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/pos/sales', [
                'type' => 'counter',
                'branch_id' => $branchId,
                'register_id' => $registerId,
                'order_date' => '2026-05-22',
                'lines' => [['product_variant_id' => $counterVariantId, 'quantity' => 1]],
            ])
            ->assertCreated()
            ->json('data.sale.id');

        $confirmedSaleId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/pos/sales', [
                'type' => 'catering',
                'branch_id' => $branchId,
                'register_id' => $registerId,
                'partner_id' => $partner->id,
                'order_date' => '2026-05-22',
                'fulfilment_date' => '2026-05-25',
                'lines' => [['product_variant_id' => $cateringVariantId, 'quantity' => 1]],
            ])
            ->assertCreated()
            ->json('data.sale.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/sales/{$confirmedSaleId}/confirm")
            ->assertSuccessful();

        foreach ([$draftSaleId, $confirmedSaleId] as $saleId) {
            $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
                ->postJson("/api/v1/pos/sales/{$saleId}/cancel")
                ->assertSuccessful()
                ->assertJsonPath('data.sale.status', 'void');
        }

        expect(StockLot::query()->count())->toBe(0)
            ->and(JournalEntry::query()->whereIn('reference_id', [$draftSaleId, $confirmedSaleId])->count())->toBe(0);
    });

    it('rejects canceling a completed sale because completed sales must be voided', function (): void {
        [, $token, $companyId, $branchId] = financeActor();
        $accounts = posVoidPostingAccounts($token, $companyId);
        $registerId = posVoidRegister($token, $companyId, $branchId, $accounts['cash']);
        $variantId = posVoidVariant($token, $companyId, 'POS-CANCEL-3', 50000);
        posVoidStockReceipt($token, $companyId, $branchId, $variantId, 5, 30000);

        $saleId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/pos/sales', [
                'type' => 'counter',
                'branch_id' => $branchId,
                'register_id' => $registerId,
                'order_date' => '2026-05-22',
                'lines' => [['product_variant_id' => $variantId, 'quantity' => 1]],
            ])
            ->assertCreated()
            ->json('data.sale.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/sales/{$saleId}/payments", [
                'method' => 'cash',
                'amount' => 50000,
            ])
            ->assertCreated();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/sales/{$saleId}/complete")
            ->assertSuccessful();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/sales/{$saleId}/cancel")
            ->assertUnprocessable();
    });

    it('enforces branch permission and company isolation when canceling a sale', function (): void {
        [, $ownerToken, $companyId, $branchId] = financeActor();
        [, $otherToken, $otherCompanyId] = financeActor();
        $cashAccountId = createAccount($ownerToken, $companyId, [
            'code' => '1-1100',
            'name' => 'Register Cash',
            'type' => 'asset',
        ]);
        $registerId = posVoidRegister($ownerToken, $companyId, $branchId, $cashAccountId);
        $variantId = posVoidVariant($ownerToken, $companyId, 'POS-CANCEL-4', 50000);

        $saleId = $this->withToken($ownerToken)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/pos/sales', [
                'type' => 'counter',
                'branch_id' => $branchId,
                'register_id' => $registerId,
                'order_date' => '2026-05-22',
                'lines' => [['product_variant_id' => $variantId, 'quantity' => 1]],
            ])
            ->assertCreated()
            ->json('data.sale.id');

        $this->withToken($otherToken)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/sales/{$saleId}/cancel")
            ->assertForbidden();

        $this->withToken($otherToken)->withHeader('X-Company-Id', (string) $otherCompanyId)
            ->postJson("/api/v1/pos/sales/{$saleId}/cancel")
            ->assertNotFound();
    });

    it('voids a completed sale by reversing journals and restoring stock', function (): void {
        [, $token, $companyId, $branchId] = financeActor();
        $accounts = posVoidPostingAccounts($token, $companyId);
        $registerId = posVoidRegister($token, $companyId, $branchId, $accounts['cash']);
        $variantId = posVoidVariant($token, $companyId, 'POS-VOID-1', 50000);
        posVoidStockReceipt($token, $companyId, $branchId, $variantId, 10, 30000);

        $saleId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/pos/sales', [
                'type' => 'counter',
                'branch_id' => $branchId,
                'register_id' => $registerId,
                'order_date' => '2026-05-22',
                'lines' => [['product_variant_id' => $variantId, 'quantity' => 2]],
            ])
            ->assertCreated()
            ->json('data.sale.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/sales/{$saleId}/payments", [
                'method' => 'cash',
                'amount' => 100000,
            ])
            ->assertCreated();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/sales/{$saleId}/complete")
            ->assertSuccessful();

        expect((float) StockLot::query()->where('product_variant_id', $variantId)->sum('remaining_quantity'))->toBe(8.0);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/sales/{$saleId}/void")
            ->assertSuccessful()
            ->assertJsonPath('data.sale.status', 'void');

        expect((float) StockLot::query()->where('product_variant_id', $variantId)->sum('remaining_quantity'))->toBe(10.0);
        expect(JournalEntry::query()->where('reference_id', $saleId)->where('status', 'void')->count())->toBe(2)
            ->and(JournalEntry::query()->where('reference_id', $saleId)->where('entry_number', 'like', 'REV-%')->count())->toBe(2);
    });
});
