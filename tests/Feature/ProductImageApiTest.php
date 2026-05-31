<?php

use App\Modules\Inventory\Models\Price;
use App\Modules\Inventory\Models\PriceList;
use App\Modules\Inventory\Models\ProductUnit;
use App\Modules\Inventory\Models\ProductVariant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
    Storage::fake('public');
});

function productImageApiKey(string $token, int $companyId): string
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

function productWithPricedUnit(string $token, int $companyId, string $sku): array
{
    $uom = createUnit($token, $companyId, 'BOX');
    $productId = createProduct($token, $companyId, [
        'name' => 'Nasi Box Image Test',
        'base_uom_id' => $uom,
        'variants' => [['sku' => $sku, 'name' => '25 Pax']],
    ]);

    $variantId = ProductVariant::query()
        ->where('product_id', $productId)
        ->where('sku', $sku)
        ->value('id');
    $unitId = createProductUnit($token, $companyId, $productId, $sku, []);

    $priceList = PriceList::query()->create([
        'company_id' => $companyId,
        'name' => 'Image Price List',
        'is_default' => true,
        'is_active' => true,
    ]);

    Price::query()->create([
        'price_list_id' => $priceList->id,
        'product_variant_id' => $variantId,
        'product_unit_id' => $unitId,
        'price' => 1250000,
        'effective_from' => '2026-01-01',
    ]);

    return [$productId, $variantId, $unitId];
}

describe('product images', function (): void {
    it('uploads a product image and returns image metadata in product resources', function (): void {
        [, $token, $companyId] = inventoryActor();
        [$productId] = productWithPricedUnit($token, $companyId, 'IMG-PROD-1');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->post("/api/v1/inventory/products/{$productId}/images", [
                'image' => UploadedFile::fake()->image('nasi-box.jpg', 640, 480),
                'alt_text' => 'Nasi Box Premium',
                'is_primary' => true,
                'sort_order' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('data.image.alt_text', 'Nasi Box Premium')
            ->assertJsonPath('data.image.is_primary', true)
            ->assertJsonPath('data.image.width', 640)
            ->assertJsonPath('data.image.height', 480);

        $response = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/products/{$productId}")
            ->assertSuccessful()
            ->assertJsonPath('data.product.images.0.alt_text', 'Nasi Box Premium');

        Storage::disk('public')->assertExists($response->json('data.product.images.0.path'));
    });

    it('copies a remote product-unit image before storing it', function (): void {
        [, $token, $companyId] = inventoryActor();
        [, , $unitId] = productWithPricedUnit($token, $companyId, 'IMG-UNIT-1');

        Http::fake([
            'images.example.test/*' => Http::response(
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='),
                200,
                ['Content-Type' => 'image/png'],
            ),
        ]);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/inventory/product-units/{$unitId}/images", [
                'remote_url' => 'https://images.example.test/menu.png',
                'alt_text' => 'Product Unit Image',
                'is_primary' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.image.original_url', 'https://images.example.test/menu.png')
            ->assertJsonPath('data.image.mime_type', 'image/png');

        $image = ProductUnit::query()->with('images')->findOrFail($unitId)->images->first();

        expect($image)->not->toBeNull();
        Storage::disk('public')->assertExists($image->path);
    });

    it('exposes product and product-unit images through the external product endpoint', function (): void {
        [, $token, $companyId] = inventoryActor();
        $apiKey = productImageApiKey($token, $companyId);
        [$productId, , $unitId] = productWithPricedUnit($token, $companyId, 'IMG-EXT-1');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->post("/api/v1/inventory/products/{$productId}/images", [
                'image' => UploadedFile::fake()->image('product.jpg', 320, 240),
                'alt_text' => 'External Product Image',
                'is_primary' => true,
            ])
            ->assertCreated();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->post("/api/v1/inventory/product-units/{$unitId}/images", [
                'image' => UploadedFile::fake()->image('unit.jpg', 320, 240),
                'alt_text' => 'External SKU Image',
                'is_primary' => true,
            ])
            ->assertCreated();

        $this->withHeader('X-API-Key', $apiKey)
            ->getJson('/api/v1/external/products')
            ->assertSuccessful()
            ->assertJsonPath('data.products.0.images.0.alt_text', 'External Product Image')
            ->assertJsonPath('data.products.0.variants.0.product_unit.images.0.alt_text', 'External SKU Image');
    });

    it('rejects non-image remote content', function (): void {
        [, $token, $companyId] = inventoryActor();
        [, , $unitId] = productWithPricedUnit($token, $companyId, 'IMG-BAD-1');

        Http::fake([
            'images.example.test/*' => Http::response('not an image', 200, ['Content-Type' => 'text/plain']),
        ]);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/inventory/product-units/{$unitId}/images", [
                'remote_url' => 'https://images.example.test/not-image.txt',
                'alt_text' => 'Bad Image',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['remote_url']);
    });
});
