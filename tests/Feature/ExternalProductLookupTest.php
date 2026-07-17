<?php

use App\Modules\Inventory\Models\Price;
use App\Modules\Inventory\Models\PriceList;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\ProductBranchAvailability;
use App\Modules\Inventory\Models\ProductUnit;
use App\Modules\Inventory\Models\ProductVariant;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
});

function createExternalLookupKey(string $token, int $companyId): string
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

function createPricedVariant(string $token, int $companyId, array $data): int
{
    $uom = createUnit($token, $companyId, $data['uom'] ?? ('pcs'.uniqid()));
    $productId = createProduct($token, $companyId, [
        'name' => $data['product_name'],
        'base_uom_id' => $uom,
        'status' => $data['product_status'] ?? 'active',
        'variants' => [
            [
                'sku' => $data['sku'],
                'name' => $data['variant_name'] ?? null,
                'is_active' => $data['variant_active'] ?? true,
            ],
        ],
    ]);

    $variantId = ProductVariant::query()
        ->where('product_id', $productId)
        ->where('sku', $data['sku'])
        ->value('id');

    $priceList = PriceList::query()->create([
        'company_id' => $companyId,
        'name' => $data['price_list_name'] ?? ('Default '.uniqid()),
        'is_default' => true,
        'is_active' => true,
    ]);

    Price::query()->create([
        'price_list_id' => $priceList->id,
        'product_variant_id' => $variantId,
        'price' => $data['price'],
        'effective_from' => '2026-01-01',
    ]);

    return $variantId;
}

describe('External product lookup', function (): void {
    it('lists active available products and prices for the api key company only', function (): void {
        [, $tokenA, $companyA, $branchA] = inventoryActor();
        [, $tokenB, $companyB] = inventoryActor();
        $apiKey = createExternalLookupKey($tokenA, $companyA);

        $visibleVariantId = createPricedVariant($tokenA, $companyA, [
            'product_name' => 'Nasi Box Rendang',
            'sku' => 'EXT-NASI-BOX',
            'variant_name' => 'Regular',
            'price' => 125000,
        ]);

        createPricedVariant($tokenA, $companyA, [
            'product_name' => 'Inactive Menu',
            'sku' => 'EXT-INACTIVE-PRODUCT',
            'product_status' => 'inactive',
            'price' => 1000,
        ]);

        $unavailableVariantId = createPricedVariant($tokenA, $companyA, [
            'product_name' => 'Unavailable Menu',
            'sku' => 'EXT-UNAVAILABLE',
            'price' => 2000,
        ]);

        ProductBranchAvailability::query()->create([
            'company_id' => $companyA,
            'branch_id' => $branchA,
            'product_variant_id' => $unavailableVariantId,
            'is_available' => false,
        ]);

        createPricedVariant($tokenB, $companyB, [
            'product_name' => 'Foreign Company Menu',
            'sku' => 'EXT-FOREIGN',
            'price' => 999999,
        ]);

        $visibleProduct = Product::query()->findOrFail(
            ProductVariant::query()->whereKey($visibleVariantId)->value('product_id'),
        );

        $visibleProduct->images()->create([
            'company_id' => $companyA,
            'path' => 'products/nasi-box.jpg',
            'url' => 'https://cdn.example.test/products/nasi-box.jpg',
            'original_url' => 'https://cdn.example.test/products/nasi-box-original.jpg',
            'alt_text' => 'Nasi Box Rendang',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 2048,
            'width' => 800,
            'height' => 600,
            'is_primary' => true,
            'sort_order' => 1,
        ]);

        $productUnit = ProductUnit::query()->create([
            'product_id' => $visibleProduct->id,
            'company_id' => $companyA,
            'sku' => 'EXT-NASI-BOX-UNIT',
            'barcode' => '8990000000017',
            'name' => 'Box of 1',
            'is_active' => true,
        ]);

        $productUnit->images()->create([
            'company_id' => $companyA,
            'path' => 'units/nasi-box-unit.jpg',
            'url' => 'https://cdn.example.test/units/nasi-box-unit.jpg',
            'is_primary' => false,
            'sort_order' => 2,
        ]);

        Price::query()
            ->where('product_variant_id', $visibleVariantId)
            ->update(['product_unit_id' => $productUnit->id]);

        $response = $this->withHeader('X-API-Key', $apiKey)
            ->getJson("/api/v1/external/products?branch_id={$branchA}")
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.products')
            ->assertJsonPath('data.products.0.name', 'Nasi Box Rendang')
            ->assertJsonPath('data.products.0.variants.0.id', $visibleVariantId)
            ->assertJsonPath('data.products.0.variants.0.price', '125000.0000')
            ->assertJsonMissing(['sku' => 'EXT-INACTIVE-PRODUCT'])
            ->assertJsonMissing(['sku' => 'EXT-UNAVAILABLE'])
            ->assertJsonMissing(['sku' => 'EXT-FOREIGN']);

        $response
            ->assertJsonPath('data.products.0.images.0.url', 'https://cdn.example.test/products/nasi-box.jpg')
            ->assertJsonPath('data.products.0.images.0.is_primary', true)
            ->assertJsonPath('data.products.0.variants.0.product_unit.id', $productUnit->id)
            ->assertJsonPath('data.products.0.variants.0.product_unit.sku', 'EXT-NASI-BOX-UNIT')
            ->assertJsonPath('data.products.0.variants.0.product_unit.images.0.url', 'https://cdn.example.test/units/nasi-box-unit.jpg');

        // Guard the exact external payload shape (keys and their order) so the
        // contract consumed by external clients cannot silently drift.
        $imageKeys = ['id', 'url', 'original_url', 'alt_text', 'mime_type', 'size_bytes', 'width', 'height', 'is_primary', 'sort_order'];
        $product = $response->json('data.products.0');

        expect(array_keys($product))->toBe(['id', 'name', 'description', 'attributes', 'images', 'variants'])
            ->and(array_keys($product['images'][0]))->toBe($imageKeys)
            ->and(array_keys($product['variants'][0]))->toBe([
                'id', 'product_id', 'sku', 'barcode', 'name', 'attributes',
                'price', 'maximum_retail_price', 'currency_id', 'product_unit',
            ])
            ->and(array_keys($product['variants'][0]['product_unit']))->toBe(['id', 'sku', 'barcode', 'name', 'images'])
            ->and(array_keys($product['variants'][0]['product_unit']['images'][0]))->toBe($imageKeys);
    });

    it('resolves variant price and rejects cross-company variants', function (): void {
        [, $tokenA, $companyA] = inventoryActor();
        [, $tokenB, $companyB] = inventoryActor();
        $apiKey = createExternalLookupKey($tokenA, $companyA);

        $variantA = createPricedVariant($tokenA, $companyA, [
            'product_name' => 'Snack Box',
            'sku' => 'EXT-SNACK',
            'price' => 45000,
        ]);

        $variantB = createPricedVariant($tokenB, $companyB, [
            'product_name' => 'Foreign Snack Box',
            'sku' => 'EXT-SNACK-FOREIGN',
            'price' => 55000,
        ]);

        $priceResponse = $this->withHeader('X-API-Key', $apiKey)
            ->getJson("/api/v1/external/products/{$variantA}/price?on=2026-05-29")
            ->assertSuccessful()
            ->assertJsonPath('data.product_variant_id', $variantA)
            ->assertJsonPath('data.price', '45000.0000');

        // Guard the exact external price payload shape (keys and their order).
        expect(array_keys($priceResponse->json('data')))
            ->toBe(['product_variant_id', 'price', 'maximum_retail_price', 'currency_id']);

        $this->withHeader('X-API-Key', $apiKey)
            ->getJson("/api/v1/external/products/{$variantB}/price?on=2026-05-29")
            ->assertNotFound();
    });

    it('rejects invalid external api keys on product lookup routes', function (): void {
        [, $token, $companyId] = inventoryActor();
        $apiKey = createExternalLookupKey($token, $companyId);

        $this->getJson('/api/v1/external/products')
            ->assertUnauthorized();

        $this->withHeader('X-API-Key', 'not-a-real-key')
            ->getJson('/api/v1/external/products')
            ->assertUnauthorized();

        $this->withHeader('X-API-Key', $apiKey)
            ->getJson('/api/v1/external/products')
            ->assertSuccessful();
    });
});
