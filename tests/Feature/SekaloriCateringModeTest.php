<?php

use App\Modules\Inventory\Models\Price;
use App\Modules\Inventory\Models\PriceList;
use App\Modules\Inventory\Models\ProductVariant;
use App\Modules\Partners\Models\Partner;
use App\Modules\Platform\Services\SettingsManager;
use Database\Seeders\CurrencySeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
    $this->seed(CurrencySeeder::class);
});

function enableCateringOnlyMode(int $companyId): void
{
    app(SettingsManager::class)->set($companyId, 'pos', 'catering_only', true);
    app(SettingsManager::class)->set($companyId, 'inventory', 'hide_catering_restricted_features', true);
}

function cateringModeVariant(string $token, int $companyId, string $sku = 'CAT-MODE-1'): int
{
    $uom = createUnit($token, $companyId, 'BOX');
    $productId = createProduct($token, $companyId, [
        'name' => 'Catering Mode Menu',
        'base_uom_id' => $uom,
        'variants' => [['sku' => $sku, 'name' => 'Box']],
    ]);
    $variantId = ProductVariant::query()
        ->where('product_id', $productId)
        ->where('sku', $sku)
        ->value('id');
    $priceList = PriceList::query()->create([
        'company_id' => $companyId,
        'name' => 'Catering Mode Price',
        'is_default' => true,
        'is_active' => true,
    ]);
    Price::query()->create([
        'price_list_id' => $priceList->id,
        'product_variant_id' => $variantId,
        'price' => 100000,
        'effective_from' => '2026-01-01',
    ]);

    return $variantId;
}

describe('SEKALORI catering-only configuration', function (): void {
    it('rejects counter sales while allowing manual catering orders without registers or shifts', function (): void {
        [, $token, $companyId, $branchId] = inventoryActor();
        enableCateringOnlyMode($companyId);
        $variantId = cateringModeVariant($token, $companyId);
        $partner = Partner::query()->create([
            'company_id' => $companyId,
            'type' => 'customer',
            'name' => 'Catering Customer',
            'email' => 'customer@example.test',
            'status' => 'active',
        ]);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/pos/sales', [
                'type' => 'counter',
                'branch_id' => $branchId,
                'lines' => [['product_variant_id' => $variantId, 'quantity' => 1]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);

        $saleId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/pos/sales', [
                'type' => 'catering',
                'branch_id' => $branchId,
                'partner_id' => $partner->id,
                'fulfilment_date' => '2026-06-10',
                'lines' => [['product_variant_id' => $variantId, 'quantity' => 1]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.sale.type', 'catering')
            ->json('data.sale.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/sales/{$saleId}/confirm")
            ->assertSuccessful()
            ->assertJsonPath('data.sale.status', 'confirmed');
    });

    it('blocks register, shift, payment, report, promotion, pricing, transfer, and issue endpoints in catering-only mode', function (): void {
        [, $token, $companyId, $branchId] = inventoryActor();
        enableCateringOnlyMode($companyId);
        $variantId = cateringModeVariant($token, $companyId, 'CAT-MODE-2');
        $partner = Partner::query()->create([
            'company_id' => $companyId,
            'type' => 'customer',
            'name' => 'Restricted Customer',
            'status' => 'active',
        ]);

        $saleId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/pos/sales', [
                'type' => 'catering',
                'branch_id' => $branchId,
                'partner_id' => $partner->id,
                'fulfilment_date' => '2026-06-10',
                'lines' => [['product_variant_id' => $variantId, 'quantity' => 1]],
            ])
            ->json('data.sale.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/pos/registers')
            ->assertForbidden();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/pos/shifts')
            ->assertForbidden();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/sales/{$saleId}/payments", [
                'method' => 'cash',
                'amount' => 100000,
            ])
            ->assertForbidden();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/pos/reports/sales?from=2026-06-01&to=2026-06-30')
            ->assertForbidden();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/inventory/discounts')
            ->assertForbidden();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/inventory/price-lists')
            ->assertForbidden();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/inventory/stock/issues', [
                'branch_id' => $branchId,
                'product_variant_id' => $variantId,
                'quantity' => 1,
            ])
            ->assertForbidden();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/inventory/stock/transfers', [
                'from_branch_id' => $branchId,
                'to_branch_id' => $branchId,
                'items' => [['product_variant_id' => $variantId, 'quantity' => 1]],
            ])
            ->assertForbidden();
    });
});
