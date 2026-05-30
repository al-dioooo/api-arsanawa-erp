<?php

use App\Modules\Inventory\Models\Price;
use App\Modules\Inventory\Models\PriceList;
use App\Modules\Inventory\Models\ProductVariant;
use App\Modules\Partners\Models\Partner;
use App\Modules\Pos\Models\Sale;
use Database\Seeders\CurrencySeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
    $this->seed(CurrencySeeder::class);
});

function createExternalOrderKey(string $token, int $companyId): string
{
    return test()->withToken($token)
        ->withHeader('X-Company-Id', (string) $companyId)
        ->postJson("/api/v1/organization/companies/{$companyId}/api-keys", [
            'name' => 'SEKALORI Landing Page',
            'source_channel' => 'Landing Page',
        ])
        ->assertCreated()
        ->json('data.plain_text_key');
}

function createExternalOrderPricedVariant(string $token, int $companyId, string $sku, int $price): int
{
    $uom = createUnit($token, $companyId, 'pcs'.uniqid());
    $productId = createProduct($token, $companyId, [
        'name' => 'External Catering Menu '.$sku,
        'base_uom_id' => $uom,
        'variants' => [['sku' => $sku, 'name' => $sku]],
    ]);

    $variantId = ProductVariant::query()
        ->where('product_id', $productId)
        ->where('sku', $sku)
        ->value('id');

    $priceList = PriceList::query()->create([
        'company_id' => $companyId,
        'name' => 'External Price List '.$sku,
        'is_default' => true,
        'is_active' => true,
    ]);

    Price::query()->create([
        'price_list_id' => $priceList->id,
        'product_variant_id' => $variantId,
        'price' => $price,
        'effective_from' => '2026-01-01',
    ]);

    return $variantId;
}

function externalOrderPayload(int $branchId, int $variantId, string $reference = 'landing-checkout-123'): array
{
    return [
        'external_reference' => $reference,
        'branch_id' => $branchId,
        'customer' => [
            'name' => 'Budi Santoso',
            'email' => 'budi@example.test',
            'phone' => '+628123456789',
        ],
        'fulfilment_date' => '2026-06-10',
        'delivery_address' => 'Jl. Sekalori No. 1',
        'notes' => 'No spicy sauce.',
        'lines' => [
            [
                'product_variant_id' => $variantId,
                'quantity' => 2,
            ],
        ],
    ];
}

describe('External catering orders', function (): void {
    it('creates an idempotent draft POS catering sale from a landing page order', function (): void {
        [, $token, $companyId, $branchId] = inventoryActor();
        $apiKey = createExternalOrderKey($token, $companyId);
        $variantId = createExternalOrderPricedVariant($token, $companyId, 'EXT-CATERING-1', 100000);
        $payload = externalOrderPayload($branchId, $variantId);

        $firstResponse = $this->withHeader('X-API-Key', $apiKey)
            ->postJson('/api/v1/external/catering-orders', $payload)
            ->assertCreated()
            ->assertJsonPath('data.sale.type', 'catering')
            ->assertJsonPath('data.sale.status', 'draft')
            ->assertJsonPath('data.sale.source', 'external')
            ->assertJsonPath('data.sale.source_channel', 'Landing Page')
            ->assertJsonPath('data.sale.external_reference', 'landing-checkout-123')
            ->assertJsonPath('data.sale.customer_name', 'Budi Santoso')
            ->assertJsonPath('data.sale.total', '200000.0000');

        $saleId = $firstResponse->json('data.sale.id');

        $this->withHeader('X-API-Key', $apiKey)
            ->postJson('/api/v1/external/catering-orders', $payload)
            ->assertSuccessful()
            ->assertJsonPath('data.sale.id', $saleId)
            ->assertJsonPath('data.idempotent', true);

        expect(Sale::query()
            ->where('company_id', $companyId)
            ->where('external_reference', 'landing-checkout-123')
            ->count())->toBe(1);

        $partner = Partner::query()
            ->where('company_id', $companyId)
            ->where('email', 'budi@example.test')
            ->first();

        expect($partner)->not->toBeNull()
            ->and($partner->type)->toBe('customer');

        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'partner_id' => $partner->id,
            'source' => 'external',
            'source_channel' => 'Landing Page',
            'external_reference' => 'landing-checkout-123',
        ]);
    });

    it('rejects cross-company branches and variants', function (): void {
        [, $tokenA, $companyA, $branchA] = inventoryActor();
        [, $tokenB, $companyB, $branchB] = inventoryActor();
        $apiKey = createExternalOrderKey($tokenA, $companyA);
        $variantA = createExternalOrderPricedVariant($tokenA, $companyA, 'EXT-COMPANY-A', 100000);
        $variantB = createExternalOrderPricedVariant($tokenB, $companyB, 'EXT-COMPANY-B', 100000);

        $this->withHeader('X-API-Key', $apiKey)
            ->postJson('/api/v1/external/catering-orders', externalOrderPayload($branchB, $variantA, 'foreign-branch'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['branch_id']);

        $this->withHeader('X-API-Key', $apiKey)
            ->postJson('/api/v1/external/catering-orders', externalOrderPayload($branchA, $variantB, 'foreign-variant'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.product_variant_id']);
    });

    it('retrieves and updates external catering order status conservatively', function (): void {
        [, $token, $companyId, $branchId] = inventoryActor();
        $apiKey = createExternalOrderKey($token, $companyId);
        $variantId = createExternalOrderPricedVariant($token, $companyId, 'EXT-CATERING-STATUS', 50000);

        $saleId = $this->withHeader('X-API-Key', $apiKey)
            ->postJson('/api/v1/external/catering-orders', externalOrderPayload($branchId, $variantId, 'landing-status-1'))
            ->assertCreated()
            ->json('data.sale.id');

        $this->withHeader('X-API-Key', $apiKey)
            ->getJson('/api/v1/external/catering-orders/landing-status-1')
            ->assertSuccessful()
            ->assertJsonPath('data.sale.id', $saleId)
            ->assertJsonPath('data.sale.status', 'draft');

        $this->withHeader('X-API-Key', $apiKey)
            ->patchJson('/api/v1/external/catering-orders/landing-status-1/status', ['status' => 'confirmed'])
            ->assertSuccessful()
            ->assertJsonPath('data.sale.status', 'confirmed');

        $this->withHeader('X-API-Key', $apiKey)
            ->patchJson('/api/v1/external/catering-orders/landing-status-1/status', ['status' => 'completed'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    });
});
