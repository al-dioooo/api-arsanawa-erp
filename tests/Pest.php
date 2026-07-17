<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Shared Test Helpers
|--------------------------------------------------------------------------
*/

/**
 * Create a user, a company they own, and return the auth context.
 * The owner holds every catalogued permission for that company.
 *
 * @return array{0: User, 1: string, 2: int, 3: int}
 *                                                   [owner, bearer token, company id, primary branch id]
 */
function inventoryActor(): array
{
    $owner = User::factory()->create();

    $token = test()->postJson('/api/v1/auth/login', [
        'login' => $owner->email,
        'password' => 'password',
    ])->json('data.access_token');

    $company = test()->withToken($token)
        ->postJson('/api/v1/organization/companies', [
            'name' => 'Inventory Co '.uniqid(),
        ])
        ->json('data');

    return [$owner, $token, $company['company']['id'], $company['primary_branch']['id']];
}

function createCategory(string $token, int $companyId, string $name, ?int $parentId = null): int
{
    return test()->withToken($token)
        ->withHeader('X-Company-Id', (string) $companyId)
        ->postJson('/api/v1/inventory/categories', ['name' => $name, 'parent_id' => $parentId])
        ->json('data.category.id');
}

function createBrand(string $token, int $companyId, string $name): int
{
    return test()->withToken($token)
        ->withHeader('X-Company-Id', (string) $companyId)
        ->postJson('/api/v1/inventory/brands', ['name' => $name])
        ->json('data.brand.id');
}

function createUnit(string $token, int $companyId, string $code, ?string $name = null): int
{
    return test()->withToken($token)
        ->withHeader('X-Company-Id', (string) $companyId)
        ->postJson('/api/v1/inventory/units-of-measure', ['name' => $name ?? mb_strtoupper($code), 'code' => $code])
        ->json('data.unit.id');
}

/**
 * @param  array<string, mixed>  $data
 */
function createProduct(string $token, int $companyId, array $data): int
{
    return test()->withToken($token)
        ->withHeader('X-Company-Id', (string) $companyId)
        ->postJson('/api/v1/inventory/products', $data)
        ->json('data.product.id');
}

function createVariantGroup(string $token, int $companyId, string $name, string $code, ?int $unitId = null): int
{
    $unitId ??= createUnit($token, $companyId, mb_strtoupper(substr($code, 0, 3)) ?: 'UNT');

    return test()->withToken($token)
        ->withHeader('X-Company-Id', (string) $companyId)
        ->postJson('/api/v1/inventory/variant-groups', [
            'name' => $name,
            'code' => $code,
            'unit_of_measure_id' => $unitId,
        ])
        ->json('data.variant_group.id');
}

function createVariant(string $token, int $companyId, int $variantGroupId, string $name, string $code): int
{
    return test()->withToken($token)
        ->withHeader('X-Company-Id', (string) $companyId)
        ->postJson('/api/v1/inventory/variants', [
            'variant_group_id' => $variantGroupId,
            'name' => $name,
            'code' => $code,
        ])
        ->json('data.variant.id');
}

/**
 * @param  array<int, int>  $variantIds
 */
function createProductUnit(string $token, int $companyId, int $productId, string $sku, array $variantIds = []): int
{
    return test()->withToken($token)
        ->withHeader('X-Company-Id', (string) $companyId)
        ->postJson('/api/v1/inventory/product-units', [
            'product_id' => $productId,
            'sku' => $sku,
            'name' => $sku,
            'variant_ids' => $variantIds,
        ])
        ->json('data.product_unit.id');
}

/**
 * Create a user, a company they own, and return the auth context.
 * Identical to inventoryActor but with a Finance-specific company name.
 *
 * @return array{0: User, 1: string, 2: int, 3: int}
 */
function financeActor(): array
{
    $owner = User::factory()->create();

    $token = test()->postJson('/api/v1/auth/login', [
        'login' => $owner->email,
        'password' => 'password',
    ])->json('data.access_token');

    $company = test()->withToken($token)
        ->postJson('/api/v1/organization/companies', [
            'name' => 'Finance Co '.uniqid(),
        ])
        ->json('data');

    return [$owner, $token, $company['company']['id'], $company['primary_branch']['id']];
}

/**
 * Create a chart of accounts entry for the given company.
 *
 * @param  array<string, mixed>  $data
 */
function createAccount(string $token, int $companyId, array $data): int
{
    return test()->withToken($token)
        ->withHeader('X-Company-Id', (string) $companyId)
        ->postJson('/api/v1/finance/accounts', $data)
        ->json('data.account.id');
}

/**
 * Create a POS register for the given branch.
 */
function posRegister(string $token, int $companyId, int $branchId): int
{
    return test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
        ->postJson('/api/v1/pos/registers', [
            'name' => 'POS Register '.uniqid(),
            'code' => 'REG-'.uniqid(),
            'branch_id' => $branchId,
        ])
        ->assertCreated()
        ->json('data.register.id');
}

/**
 * Create a product with a single variant priced on a default price list.
 */
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

/**
 * Create a POS register bound to a cash account.
 */
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

/**
 * Create the chart of accounts, account mappings, and an open May 2026 period
 * needed for POS sale posting.
 *
 * @return array{cash: int, ar: int, inventory: int, vat: int, revenue: int, cogs: int}
 */
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

/**
 * Receive stock for a variant into the given branch.
 */
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
