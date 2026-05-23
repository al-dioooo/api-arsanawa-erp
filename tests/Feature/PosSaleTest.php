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

function posPricedVariant(string $token, int $companyId, string $sku, int $price): int
{
    $uom = createUnit($token, $companyId, strtolower($sku).'-pcs');
    $productId = createProduct($token, $companyId, [
        'name' => 'POS Product '.$sku,
        'base_uom_id' => $uom,
        'variants' => [['sku' => $sku]],
    ]);

    $variantId = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
        ->getJson("/api/v1/inventory/products/{$productId}")
        ->json('data.product.variants.0.id');

    $priceListId = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
        ->postJson('/api/v1/inventory/price-lists', [
            'name' => 'POS Retail '.$sku,
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

describe('POS sales', function () {
    it('creates a draft counter sale with resolved prices and calculated tax totals', function (): void {
        [, $token, $companyId, $branchId] = financeActor();
        $registerId = posRegister($token, $companyId, $branchId);
        $variantId = posPricedVariant($token, $companyId, 'POS-SALE-1', 50000);
        $taxRate = TaxRate::create([
            'company_id' => $companyId,
            'name' => 'VAT 11%',
            'type' => 'vat',
            'rate' => 11.0000,
            'is_active' => true,
        ]);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/pos/sales', [
                'type' => 'counter',
                'branch_id' => $branchId,
                'register_id' => $registerId,
                'order_date' => '2026-05-22',
                'lines' => [
                    [
                        'product_variant_id' => $variantId,
                        'quantity' => 2,
                        'tax_rate_id' => $taxRate->id,
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.sale.status', 'draft')
            ->assertJsonPath('data.sale.subtotal', '100000.0000')
            ->assertJsonPath('data.sale.discount_total', '0.0000')
            ->assertJsonPath('data.sale.tax_total', '11000.0000')
            ->assertJsonPath('data.sale.total', '111000.0000')
            ->assertJsonPath('data.sale.lines.0.unit_price', '50000.0000')
            ->assertJsonPath('data.sale.lines.0.line_total', '111000.0000');
    });

    it('updates draft sale lines and removes omitted lines', function (): void {
        [, $token, $companyId, $branchId] = financeActor();
        $registerId = posRegister($token, $companyId, $branchId);
        $variantA = posPricedVariant($token, $companyId, 'POS-SALE-2A', 50000);
        $variantB = posPricedVariant($token, $companyId, 'POS-SALE-2B', 25000);

        $saleId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/pos/sales', [
                'type' => 'counter',
                'branch_id' => $branchId,
                'register_id' => $registerId,
                'lines' => [
                    ['product_variant_id' => $variantA, 'quantity' => 1],
                    ['product_variant_id' => $variantB, 'quantity' => 1],
                ],
            ])
            ->assertCreated()
            ->json('data.sale.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->patchJson("/api/v1/pos/sales/{$saleId}", [
                'lines' => [
                    ['product_variant_id' => $variantB, 'quantity' => 3],
                ],
            ])
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.sale.lines')
            ->assertJsonPath('data.sale.lines.0.product_variant_id', $variantB)
            ->assertJsonPath('data.sale.lines.0.quantity', '3.0000')
            ->assertJsonPath('data.sale.total', '75000.0000');
    });

    it('records split tender payments and rejects overpayment', function (): void {
        [, $token, $companyId, $branchId] = financeActor();
        $registerId = posRegister($token, $companyId, $branchId);
        $variantId = posPricedVariant($token, $companyId, 'POS-SALE-3', 111000);

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
            ->postJson("/api/v1/pos/sales/{$saleId}/payments", [
                'method' => 'cash',
                'amount' => 100000,
            ])
            ->assertCreated()
            ->assertJsonPath('data.sale.amount_paid', '100000.0000');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/sales/{$saleId}/payments", [
                'method' => 'card',
                'amount' => 11000,
                'reference' => 'CARD-001',
            ])
            ->assertCreated()
            ->assertJsonPath('data.sale.amount_paid', '111000.0000')
            ->assertJsonCount(2, 'data.sale.payments');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/sales/{$saleId}/payments", [
                'method' => 'cash',
                'amount' => 5000,
            ])
            ->assertUnprocessable();
    });

    it('lists and shows sales with filters and company isolation', function (): void {
        [, $tokenA, $companyA, $branchA] = financeActor();
        [, $tokenB, $companyB, $branchB] = financeActor();
        $registerA = posRegister($tokenA, $companyA, $branchA);
        $registerB = posRegister($tokenB, $companyB, $branchB);
        $variantA = posPricedVariant($tokenA, $companyA, 'POS-SALE-4A', 50000);
        $variantB = posPricedVariant($tokenB, $companyB, 'POS-SALE-4B', 50000);
        $partnerB = Partner::create([
            'company_id' => $companyB,
            'type' => 'customer',
            'name' => 'Company B Customer',
            'code' => 'B-CUST-001',
            'status' => 'active',
        ]);

        $saleA = $this->withToken($tokenA)->withHeader('X-Company-Id', (string) $companyA)
            ->postJson('/api/v1/pos/sales', [
                'type' => 'counter',
                'branch_id' => $branchA,
                'register_id' => $registerA,
                'lines' => [['product_variant_id' => $variantA, 'quantity' => 1]],
            ])
            ->assertCreated()
            ->json('data.sale.id');

        $this->withToken($tokenB)->withHeader('X-Company-Id', (string) $companyB)
            ->postJson('/api/v1/pos/sales', [
                'type' => 'catering',
                'branch_id' => $branchB,
                'register_id' => $registerB,
                'partner_id' => $partnerB->id,
                'customer_name' => 'Company B Customer',
                'fulfilment_date' => '2026-06-01',
                'lines' => [['product_variant_id' => $variantB, 'quantity' => 1]],
            ])
            ->assertCreated();

        $this->withToken($tokenA)->withHeader('X-Company-Id', (string) $companyA)
            ->getJson('/api/v1/pos/sales?type=counter&status=draft')
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.sales')
            ->assertJsonPath('data.sales.0.id', $saleA);

        $this->withToken($tokenA)->withHeader('X-Company-Id', (string) $companyA)
            ->getJson("/api/v1/pos/sales/{$saleA}")
            ->assertSuccessful()
            ->assertJsonPath('data.sale.id', $saleA);
    });

    it('requires POS operate permission to create sales', function (): void {
        $user = User::factory()->create();
        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.access_token');

        $this->withToken($token)
            ->postJson('/api/v1/pos/sales', [
                'type' => 'counter',
                'branch_id' => 1,
                'lines' => [],
            ])
            ->assertForbidden();
    });
});
