<?php

use Database\Seeders\CurrencySeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
    $this->seed(CurrencySeeder::class);
});

function posPromotionVariant(string $token, int $companyId, string $sku, int $price): int
{
    $uom = createUnit($token, $companyId, strtolower($sku).'-pcs');
    $productId = createProduct($token, $companyId, [
        'name' => 'Promotion Product '.$sku,
        'base_uom_id' => $uom,
        'variants' => [['sku' => $sku]],
    ]);

    $variantId = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
        ->getJson("/api/v1/inventory/products/{$productId}")
        ->json('data.product.variants.0.id');

    $priceListId = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
        ->postJson('/api/v1/inventory/price-lists', [
            'name' => 'Promotion Retail '.$sku,
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

describe('POS promotions', function () {
    it('applies an eligible percentage discount to a draft sale', function (): void {
        [, $token, $companyId, $branchId] = financeActor();
        $registerId = posRegister($token, $companyId, $branchId);
        $variantId = posPromotionVariant($token, $companyId, 'POS-PROMO-1', 100000);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/inventory/discounts', [
                'name' => 'Ten Percent',
                'calculation_type' => 'percentage',
                'value' => 10,
                'min_quantity' => 1,
                'effective_from' => '2026-01-01',
                'targets' => [['target_type' => 'variant', 'target_id' => $variantId]],
            ])
            ->assertCreated();

        $saleId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/pos/sales', [
                'type' => 'counter',
                'branch_id' => $branchId,
                'register_id' => $registerId,
                'lines' => [['product_variant_id' => $variantId, 'quantity' => 1]],
            ])
            ->assertCreated()
            ->json('data.sale.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/sales/{$saleId}/apply-promotions")
            ->assertSuccessful()
            ->assertJsonPath('data.sale.discount_total', '10000.0000')
            ->assertJsonPath('data.sale.total', '90000.0000')
            ->assertJsonCount(1, 'data.sale.promotions')
            ->assertJsonPath('data.sale.promotions.0.amount', '10000.0000');

        // The sale detail must carry applied promotions so the UI panel renders.
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/pos/sales/{$saleId}")
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.sale.promotions')
            ->assertJsonPath('data.sale.promotions.0.amount', '10000.0000');
    });

    it('adds giveaway lines only when the buy threshold is met', function (): void {
        [, $token, $companyId, $branchId] = financeActor();
        $registerId = posRegister($token, $companyId, $branchId);
        $variantA = posPromotionVariant($token, $companyId, 'POS-PROMO-2A', 10000);
        $variantB = posPromotionVariant($token, $companyId, 'POS-PROMO-2B', 5000);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/inventory/discounts', [
                'name' => 'Buy Three Get One',
                'calculation_type' => 'amount',
                'value' => 0,
                'effective_from' => '2026-01-01',
                'dependencies' => [['product_variant_id' => $variantA, 'required_quantity' => 3]],
                'giveaways' => [['product_variant_id' => $variantB, 'giveaway_quantity' => 1]],
            ])
            ->assertCreated();

        $saleId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/pos/sales', [
                'type' => 'counter',
                'branch_id' => $branchId,
                'register_id' => $registerId,
                'lines' => [['product_variant_id' => $variantA, 'quantity' => 2]],
            ])
            ->assertCreated()
            ->json('data.sale.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/sales/{$saleId}/apply-promotions")
            ->assertSuccessful()
            ->assertJsonCount(0, 'data.sale.promotions')
            ->assertJsonCount(1, 'data.sale.lines');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->patchJson("/api/v1/pos/sales/{$saleId}", [
                'lines' => [['product_variant_id' => $variantA, 'quantity' => 3]],
            ])
            ->assertSuccessful();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/sales/{$saleId}/apply-promotions")
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.sale.promotions')
            ->assertJsonCount(2, 'data.sale.lines')
            ->assertJsonPath('data.sale.lines.1.product_variant_id', $variantB)
            ->assertJsonPath('data.sale.lines.1.is_giveaway', true)
            ->assertJsonPath('data.sale.lines.1.unit_price', '0.0000');
    });

    it('adds automatic discounts on top of manual line discounts', function (): void {
        [, $token, $companyId, $branchId] = financeActor();
        $registerId = posRegister($token, $companyId, $branchId);
        $variantId = posPromotionVariant($token, $companyId, 'POS-PROMO-3', 100000);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/inventory/discounts', [
                'name' => 'Ten Thousand Off',
                'calculation_type' => 'amount',
                'value' => 10000,
                'min_quantity' => 1,
                'effective_from' => '2026-01-01',
                'targets' => [['target_type' => 'variant', 'target_id' => $variantId]],
            ])
            ->assertCreated();

        $saleId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/pos/sales', [
                'type' => 'counter',
                'branch_id' => $branchId,
                'register_id' => $registerId,
                'lines' => [
                    [
                        'product_variant_id' => $variantId,
                        'quantity' => 1,
                        'discount' => 5000,
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.sale.discount_total', '5000.0000')
            ->json('data.sale.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/sales/{$saleId}/apply-promotions")
            ->assertSuccessful()
            ->assertJsonPath('data.sale.discount_total', '15000.0000')
            ->assertJsonPath('data.sale.total', '85000.0000');
    });
});
