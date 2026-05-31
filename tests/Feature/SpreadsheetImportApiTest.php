<?php

use App\Models\User;
use App\Modules\Inventory\Models\Category;
use App\Modules\Inventory\Models\Price;
use App\Modules\Inventory\Models\PriceList;
use App\Modules\Inventory\Models\ProductUnit;
use App\Modules\Inventory\Models\ProductVariant;
use App\Modules\Organization\Models\Company;
use App\Modules\Partners\Models\Partner;
use App\Modules\Platform\Services\SettingsManager;
use App\Modules\Pos\Models\Sale;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
    $this->seed(CurrencySeeder::class);
});

function spreadsheetUpload(string $name, string $contents): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $contents);
}

function importCsv(array $headers, array $rows): string
{
    $lines = [implode(',', $headers)];

    foreach ($rows as $row) {
        $lines[] = implode(',', array_map(
            fn (string $value): string => str_contains($value, ',') ? '"'.str_replace('"', '""', $value).'"' : $value,
            $row,
        ));
    }

    return implode("\n", $lines)."\n";
}

function pricedImportVariant(string $token, int $companyId, string $sku, int $price): int
{
    $unit = createUnit($token, $companyId, 'PCS');
    $productId = createProduct($token, $companyId, [
        'name' => "Import Menu {$sku}",
        'base_uom_id' => $unit,
        'variants' => [['sku' => $sku, 'name' => $sku]],
    ]);

    $variantId = ProductVariant::query()
        ->where('product_id', $productId)
        ->where('sku', $sku)
        ->value('id');

    $priceList = PriceList::query()->create([
        'company_id' => $companyId,
        'name' => "Import Price {$sku}",
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

describe('spreadsheet import templates', function (): void {
    it('downloads POS and Inventory templates as CSV and XLSX', function (): void {
        [, $token, $companyId] = inventoryActor();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->get('/api/v1/pos/sales/imports/template.csv')
            ->assertSuccessful()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertSee('order_reference');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->get('/api/v1/pos/sales/imports/template.xlsx')
            ->assertSuccessful()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->get('/api/v1/inventory/products/imports/template.csv')
            ->assertSuccessful()
            ->assertSee('product_name')
            ->assertSee('batch_number');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->get('/api/v1/inventory/products/imports/template.xlsx')
            ->assertSuccessful()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    });
});

describe('POS catering spreadsheet imports', function (): void {
    it('previews and commits configured SEKALORI Google Form rows', function (): void {
        $this->seed(DatabaseSeeder::class);

        $company = Company::query()->where('slug', 'sekalori')->firstOrFail();
        $owner = User::query()->where('username', 'sekalori')->firstOrFail();
        $token = $this->postJson('/api/v1/auth/login', [
            'login' => 'owner@sekalori.test',
            'password' => 'sekalori1234',
        ])->assertSuccessful()->json('data.access_token');

        $sourceUrl = 'https://docs.google.com/spreadsheets/d/sekalori/export?format=csv&gid=2117219189';
        $config = app(SettingsManager::class)->get($company->id, 'pos', 'catering_form_import');
        $config['source_url'] = $sourceUrl;
        app(SettingsManager::class)->set($company->id, 'pos', 'catering_form_import', $config, null, $owner->id);

        Http::fake([
            'docs.google.com/*' => Http::response(importCsv([
                'Timestamp',
                'Nama Lengkap',
                'Nomor WhatsApp',
                'Alamat Pengiriman',
                'Jenis Menu',
                'Batch Pengiriman',
                'Metode Pembayaran',
                'Bukti Transfer',
                'Catatan',
            ], [
                ['2026-06-01 09:15:00', 'Budi Santoso', '+628123456789', 'Jl Sekalori 1', 'Indonesian Local', 'Batch 1 (09:00-11:00)', 'Transfer Bank', 'https://drive.google.test/proof-1', 'Tanpa sambal'],
            ]), 200, ['Content-Type' => 'text/csv']),
        ]);

        $preview = $this->withToken($token)->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/pos/sales/imports/configured/preview')
            ->assertSuccessful()
            ->assertJsonPath('data.import.kind', 'pos_catering_orders')
            ->assertJsonPath('data.import.source', 'url')
            ->assertJsonPath('data.import.status', 'previewed')
            ->assertJsonPath('data.import.error_count', 0)
            ->assertJsonPath('data.rows.0.normalized.branch_code', 'MAIN')
            ->assertJsonPath('data.rows.0.normalized.customer_name', 'Budi Santoso')
            ->assertJsonPath('data.rows.0.normalized.customer_phone', '+628123456789')
            ->assertJsonPath('data.rows.0.normalized.fulfilment_date', '2026-06-02')
            ->assertJsonPath('data.rows.0.normalized.fulfilment_time_window', 'Batch 1 (09:00-11:00)')
            ->assertJsonPath('data.rows.0.normalized.sku', 'SKL-BND-IDN')
            ->assertJsonPath('data.rows.0.normalized.quantity', 1)
            ->assertJsonPath('data.rows.0.normalized.payment_method', 'transfer')
            ->assertJsonPath('data.rows.0.normalized.payment_reference', 'https://drive.google.test/proof-1')
            ->json('data');

        expect($preview['rows'][0]['normalized']['order_reference'])->toStartWith('GFORM-');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/pos/sales/imports/{$preview['import']['id']}/commit")
            ->assertAccepted()
            ->assertJsonPath('data.import.status', 'completed')
            ->assertJsonPath('data.import.created_count', 1);

        $sale = Sale::query()
            ->with(['payments', 'register'])
            ->where('company_id', $company->id)
            ->where('external_reference', $preview['rows'][0]['normalized']['order_reference'])
            ->firstOrFail();
        $partner = Partner::query()->where('company_id', $company->id)->where('phone', '+628123456789')->first();

        expect($sale->status)->toBe('confirmed')
            ->and($sale->type)->toBe('catering')
            ->and($sale->source_channel)->toBe('google_form')
            ->and($sale->fulfilment_date->toDateString())->toBe('2026-06-02')
            ->and($sale->fulfilment_time_window)->toBe('Batch 1 (09:00-11:00)')
            ->and($sale->amount_paid)->toBe($sale->total)
            ->and($sale->register?->code)->toBe('GFORM-IMPORT')
            ->and($partner)->not->toBeNull()
            ->and($sale->payments)->toHaveCount(1)
            ->and($sale->payments->first()->method)->toBe('transfer')
            ->and($sale->payments->first()->reference)->toBe('https://drive.google.test/proof-1');
    });

    it('marks configured SEKALORI Google Form rows invalid when menu type is unmapped', function (): void {
        $this->seed(DatabaseSeeder::class);

        $company = Company::query()->where('slug', 'sekalori')->firstOrFail();
        $owner = User::query()->where('username', 'sekalori')->firstOrFail();
        $token = $this->postJson('/api/v1/auth/login', [
            'login' => 'owner@sekalori.test',
            'password' => 'sekalori1234',
        ])->assertSuccessful()->json('data.access_token');

        $sourceUrl = 'https://docs.google.com/spreadsheets/d/sekalori/export?format=csv&gid=2117219189';
        $config = app(SettingsManager::class)->get($company->id, 'pos', 'catering_form_import');
        $config['source_url'] = $sourceUrl;
        app(SettingsManager::class)->set($company->id, 'pos', 'catering_form_import', $config, null, $owner->id);

        Http::fake([
            'docs.google.com/*' => Http::response(importCsv([
                'Timestamp',
                'Nama Lengkap',
                'Nomor WhatsApp',
                'Alamat Pengiriman',
                'Jenis Menu',
                'Batch Pengiriman',
                'Metode Pembayaran',
                'Bukti Transfer',
                'Catatan',
            ], [
                ['2026-06-01 09:15:00', 'Bad Menu', '+628000000000', 'Jl Sekalori 2', 'Korean', 'Batch 2 (13:00-15:00)', 'COD', '', ''],
            ]), 200, ['Content-Type' => 'text/csv']),
        ]);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/pos/sales/imports/configured/preview')
            ->assertSuccessful()
            ->assertJsonPath('data.import.status', 'invalid')
            ->assertJsonPath('data.import.error_count', 1)
            ->assertJsonPath('data.rows.0.errors.menu_type.0', 'Menu type is not configured.');
    });

    it('retries configured SEKALORI Google Form exports without the invented default gid', function (): void {
        $this->seed(DatabaseSeeder::class);

        $company = Company::query()->where('slug', 'sekalori')->firstOrFail();
        $owner = User::query()->where('username', 'sekalori')->firstOrFail();
        $token = $this->postJson('/api/v1/auth/login', [
            'login' => 'owner@sekalori.test',
            'password' => 'sekalori1234',
        ])->assertSuccessful()->json('data.access_token');

        $sourceUrl = 'https://docs.google.com/spreadsheets/d/sekalori/export?format=csv&gid=0';
        $config = app(SettingsManager::class)->get($company->id, 'pos', 'catering_form_import');
        $config['source_url'] = $sourceUrl;
        app(SettingsManager::class)->set($company->id, 'pos', 'catering_form_import', $config, null, $owner->id);

        Http::fake(function (Illuminate\Http\Client\Request $request) {
            $url = $request->url();

            if (str_contains($url, 'gid=0')) {
                return Http::response('<html>Invalid gid</html>', 400, ['Content-Type' => 'text/html']);
            }

            return Http::response(importCsv([
                'Timestamp',
                'Nama Lengkap',
                'Nomor WhatsApp',
                'Alamat Pengiriman',
                'Jenis Menu',
                'Batch Pengiriman',
                'Metode Pembayaran',
                'Bukti Transfer',
                'Catatan',
            ], [
                ['2026-06-01 09:15:00', 'Budi Santoso', '+628123456789', 'Jl Sekalori 1', 'Western', 'Batch 1 (09:00-11:00)', 'COD', '', ''],
            ]), 200, ['Content-Type' => 'text/csv']);
        });

        $this->withToken($token)->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/pos/sales/imports/configured/preview')
            ->assertSuccessful()
            ->assertJsonPath('data.import.status', 'previewed')
            ->assertJsonPath('data.rows.0.normalized.sku', 'SKL-BND-WST');

        Http::assertSentCount(2);
    });

    it('rejects configured Google Form preview for non-SEKALORI companies', function (): void {
        [, $token, $companyId] = inventoryActor();

        app(SettingsManager::class)->set($companyId, 'pos', 'catering_form_import', [
            'source_url' => 'https://docs.google.com/spreadsheets/d/example/export?format=csv&gid=0',
        ]);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/pos/sales/imports/configured/preview')
            ->assertForbidden();
    });

    it('previews and commits a confirmed catering sale from a CSV upload', function (): void {
        [, $token, $companyId] = inventoryActor();
        pricedImportVariant($token, $companyId, 'SKL-IMP-BOX', 125000);

        $csv = importCsv([
            'order_reference',
            'branch_code',
            'customer_name',
            'customer_email',
            'customer_phone',
            'order_date',
            'fulfilment_date',
            'delivery_address',
            'sku',
            'quantity',
            'unit_price',
            'discount',
            'notes',
        ], [
            ['ORD-001', 'MAIN', 'Budi Santoso', 'budi@example.test', '+628123456789', '2026-06-01', '2026-06-05', 'Jl Sekalori 1', 'SKL-IMP-BOX', '2', '125000', '0', 'No spicy sauce'],
        ]);

        $importId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->post('/api/v1/pos/sales/imports/inspect', [
                'file' => spreadsheetUpload('orders.csv', $csv),
            ])
            ->assertCreated()
            ->assertJsonPath('data.import.kind', 'pos_catering_orders')
            ->assertJsonPath('data.sheets.0.supported', true)
            ->json('data.import.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/sales/imports/{$importId}/preview", [
                'sheet_name' => 'orders.csv',
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.import.status', 'previewed')
            ->assertJsonPath('data.import.error_count', 0)
            ->assertJsonPath('data.rows.0.normalized.order_reference', 'ORD-001');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/sales/imports/{$importId}/commit")
            ->assertAccepted()
            ->assertJsonPath('data.import.status', 'completed')
            ->assertJsonPath('data.import.created_count', 1);

        $sale = Sale::query()->where('company_id', $companyId)->where('external_reference', 'ORD-001')->first();
        $partner = Partner::query()->where('company_id', $companyId)->where('email', 'budi@example.test')->first();

        expect($sale)->not->toBeNull()
            ->and($sale->status)->toBe('confirmed')
            ->and($sale->type)->toBe('catering')
            ->and($sale->partner_id)->toBe($partner->id)
            ->and($sale->lines)->toHaveCount(1);
    });

    it('rejects commit when preview has a row error', function (): void {
        [, $token, $companyId] = inventoryActor();

        $csv = importCsv([
            'order_reference',
            'branch_code',
            'customer_name',
            'customer_email',
            'customer_phone',
            'order_date',
            'fulfilment_date',
            'delivery_address',
            'sku',
            'quantity',
            'unit_price',
            'discount',
            'notes',
        ], [
            ['ORD-BAD', 'MISSING', 'Bad Customer', 'bad@example.test', '', '2026-06-01', '2026-06-05', '', 'NO-SKU', '2', '125000', '0', ''],
        ]);

        $importId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->post('/api/v1/pos/sales/imports/inspect', [
                'file' => spreadsheetUpload('orders.csv', $csv),
            ])
            ->assertCreated()
            ->json('data.import.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/sales/imports/{$importId}/preview", [
                'sheet_name' => 'orders.csv',
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.import.status', 'invalid')
            ->assertJsonPath('data.import.error_count', 2);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/sales/imports/{$importId}/commit")
            ->assertUnprocessable();

        $this->assertDatabaseMissing('sales', ['external_reference' => 'ORD-BAD']);
    });

    it('inspects a public Google Sheets CSV export link', function (): void {
        [, $token, $companyId] = inventoryActor();

        Http::fake([
            'docs.google.com/*' => Http::response("order_reference,branch_code\nORD-1,MAIN\n", 200, [
                'Content-Type' => 'text/csv',
            ]),
        ]);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/pos/sales/imports/inspect', [
                'source_url' => 'https://docs.google.com/spreadsheets/d/example/export?format=csv&gid=0',
            ])
            ->assertCreated()
            ->assertJsonPath('data.import.source', 'url')
            ->assertJsonPath('data.sheets.0.supported', true);
    });
});

describe('Inventory product spreadsheet imports', function (): void {
    it('creates product master data, pricing, opening stock, and batch metadata from CSV', function (): void {
        [, $token, $companyId] = inventoryActor();

        $csv = importCsv([
            'product_name',
            'product_description',
            'category_path',
            'brand_name',
            'base_uom_code',
            'sku',
            'product_unit_name',
            'barcode',
            'variant_values',
            'price_list_name',
            'currency_code',
            'price',
            'effective_from',
            'branch_code',
            'opening_quantity',
            'unit_cost',
            'batch_number',
            'received_at',
            'expiry_date',
            'production_date',
            'status',
            'track_stock',
        ], [
            ['Nasi Box Premium', 'Premium catering package', 'Catering>Nasi Box', 'SEKALORI', 'BOX', 'SKL-NBP-25-AYM', 'Nasi Box Premium 25 Pax Ayam', '899700000001', 'Package Size=25 Pax;Protein=Ayam', 'SEKALORI Retail', 'IDR', '1250000', '2026-06-01', 'MAIN', '12', '760000', 'BATCH-JUN-01', '2026-06-01', '2026-06-10', '2026-05-31', 'active', 'true'],
        ]);

        $importId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->post('/api/v1/inventory/products/imports/inspect', [
                'file' => spreadsheetUpload('products.csv', $csv),
            ])
            ->assertCreated()
            ->assertJsonPath('data.import.kind', 'inventory_products')
            ->json('data.import.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/inventory/products/imports/{$importId}/preview", [
                'sheet_name' => 'products.csv',
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.import.error_count', 0)
            ->assertJsonPath('data.rows.0.normalized.sku', 'SKL-NBP-25-AYM');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/inventory/products/imports/{$importId}/commit")
            ->assertAccepted()
            ->assertJsonPath('data.import.status', 'completed');

        $unit = ProductUnit::query()->where('company_id', $companyId)->where('sku', 'SKL-NBP-25-AYM')->first();

        expect($unit)->not->toBeNull()
            ->and($unit->product->name)->toBe('Nasi Box Premium')
            ->and($unit->variants)->toHaveCount(2);

        $this->assertDatabaseHas('prices', [
            'product_unit_id' => $unit->id,
            'price' => '1250000.0000',
        ]);

        $this->assertDatabaseHas('stock_lots', [
            'product_unit_id' => $unit->id,
            'lot_number' => 'BATCH-JUN-01',
            'production_date' => '2026-05-31',
            'remaining_quantity' => '12.0000',
        ]);
    });

    it('rejects inventory imports when category_path resolves to an existing parent category', function (): void {
        [, $token, $companyId] = inventoryActor();
        $parent = createCategory($token, $companyId, 'Catering');
        createCategory($token, $companyId, 'Nasi Box', $parent);

        $csv = importCsv([
            'product_name',
            'category_path',
            'base_uom_code',
            'sku',
            'product_unit_name',
        ], [
            ['Parent Category Menu', 'Catering', 'BOX', 'PARENT-IMPORT-1', 'Parent Category Menu'],
        ]);

        $importId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->post('/api/v1/inventory/products/imports/inspect', [
                'file' => spreadsheetUpload('parent-category-products.csv', $csv),
            ])
            ->assertCreated()
            ->json('data.import.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/inventory/products/imports/{$importId}/preview", [
                'sheet_name' => 'parent-category-products.csv',
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.import.error_count', 1)
            ->assertJsonPath('data.rows.0.errors.category_path.0', 'Products can only be assigned to the lowest category level.');
    });

    it('accepts inventory imports for existing leaf and new final leaf category paths', function (): void {
        [, $token, $companyId] = inventoryActor();
        $parent = createCategory($token, $companyId, 'Catering');
        createCategory($token, $companyId, 'Nasi Box', $parent);

        $csv = importCsv([
            'product_name',
            'category_path',
            'base_uom_code',
            'sku',
            'product_unit_name',
        ], [
            ['Existing Leaf Menu', 'Catering>Nasi Box', 'BOX', 'LEAF-IMPORT-1', 'Existing Leaf Menu'],
            ['New Leaf Menu', 'Catering>Snack Box', 'BOX', 'LEAF-IMPORT-2', 'New Leaf Menu'],
        ]);

        $importId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->post('/api/v1/inventory/products/imports/inspect', [
                'file' => spreadsheetUpload('leaf-category-products.csv', $csv),
            ])
            ->assertCreated()
            ->json('data.import.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/inventory/products/imports/{$importId}/preview", [
                'sheet_name' => 'leaf-category-products.csv',
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.import.error_count', 0);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/inventory/products/imports/{$importId}/commit")
            ->assertAccepted()
            ->assertJsonPath('data.import.status', 'completed');

        expect(Category::query()
            ->where('company_id', $companyId)
            ->where('name', 'Snack Box')
            ->exists())->toBeTrue();
    });

    it('blocks inventory imports above the 1000 row limit', function (): void {
        [, $token, $companyId] = inventoryActor();
        $headers = [
            'product_name',
            'base_uom_code',
            'sku',
            'product_unit_name',
            'branch_code',
        ];
        $rows = [];

        for ($i = 1; $i <= 1001; $i++) {
            $rows[] = ['Product '.$i, 'PCS', 'SKU-'.$i, 'Unit '.$i, 'MAIN'];
        }

        $importId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->post('/api/v1/inventory/products/imports/inspect', [
                'file' => spreadsheetUpload('too-many-products.csv', importCsv($headers, $rows)),
            ])
            ->assertCreated()
            ->json('data.import.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/inventory/products/imports/{$importId}/preview", [
                'sheet_name' => 'too-many-products.csv',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sheet_name']);
    });
});
