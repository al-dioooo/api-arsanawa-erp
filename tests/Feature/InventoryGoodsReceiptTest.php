<?php

use App\Modules\Inventory\Models\GoodsReceipt;
use App\Modules\Partners\Models\Partner;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
});

/**
 * Create a supplier partner for the given company.
 */
function createSupplier(int $companyId, string $name = 'Supplier Inc'): Partner
{
    return Partner::create([
        'company_id' => $companyId,
        'type' => 'vendor',
        'name' => $name,
        'code' => 'SUP-'.uniqid(),
        'status' => 'active',
    ]);
}

/**
 * Seed a company with a product unit ready to receive stock, returning
 * [token, companyId, branchId, productUnitId, supplier].
 *
 * @return array{0: string, 1: int, 2: int, 3: int, 4: Partner}
 */
function goodsReceiptFixtures(): array
{
    [, $token, $companyId, $branchId] = inventoryActor();
    $uom = createUnit($token, $companyId, 'kg');
    $productId = createProduct($token, $companyId, [
        'name' => 'Tepung Terigu',
        'base_uom_id' => $uom,
    ]);
    $productUnitId = createProductUnit($token, $companyId, $productId, 'BAHAN-TERIGU-25');
    $supplier = createSupplier($companyId);

    return [$token, $companyId, $branchId, $productUnitId, $supplier];
}

describe('Inventory goods receipts (STB)', function () {
    it('records a goods receipt with supplier, delivery note and lines, linking each line to the stock movement it creates', function (): void {
        [$token, $companyId, $branchId, $productUnitId, $supplier] = goodsReceiptFixtures();

        $response = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/goods-receipts', [
                'branch_id' => $branchId,
                'partner_id' => $supplier->id,
                'delivery_note_number' => 'SJ-2026-0007',
                'receipt_date' => '2026-07-10',
                'notes' => 'Kiriman pagi',
                'lines' => [
                    [
                        'product_unit_id' => $productUnitId,
                        'quantity' => 25,
                        'unit_cost' => 12000,
                        'lot_number' => 'LOT-A1',
                        'expiry_date' => '2027-01-01',
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.goods_receipt.delivery_note_number', 'SJ-2026-0007')
            ->assertJsonPath('data.goods_receipt.partner.name', $supplier->name)
            ->assertJsonPath('data.goods_receipt.partner_id', $supplier->id)
            ->assertJsonPath('data.goods_receipt.receipt_date', '2026-07-10')
            ->assertJsonPath('data.goods_receipt.status', 'received')
            ->assertJsonPath('data.goods_receipt.item_count', 1)
            ->assertJsonPath('data.goods_receipt.total_cost', '300000.0000')
            ->assertJsonPath('data.goods_receipt.lines.0.line_total', '300000.0000');

        $receiptId = $response->json('data.goods_receipt.id');
        $lineMovementId = $response->json('data.goods_receipt.lines.0.stock_movement_id');
        $lineLotId = $response->json('data.goods_receipt.lines.0.stock_lot_id');

        // The line links to a real receipt movement + lot.
        expect($lineMovementId)->not->toBeNull();
        expect($lineLotId)->not->toBeNull();

        $this->assertDatabaseHas('goods_receipts', [
            'id' => $receiptId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'partner_id' => $supplier->id,
            'delivery_note_number' => 'SJ-2026-0007',
            'status' => 'received',
        ]);

        $this->assertDatabaseHas('goods_receipt_lines', [
            'goods_receipt_id' => $receiptId,
            'stock_movement_id' => $lineMovementId,
            'stock_lot_id' => $lineLotId,
            'quantity' => '25.0000',
        ]);

        // The stock ledger movement points back at the goods receipt document.
        $this->assertDatabaseHas('stock_movements', [
            'id' => $lineMovementId,
            'type' => 'receipt',
            'reference_type' => GoodsReceipt::class,
            'reference_id' => $receiptId,
            'quantity' => '25.0000',
        ]);

        $this->assertDatabaseHas('stock_lots', [
            'id' => $lineLotId,
            'lot_number' => 'LOT-A1',
            'remaining_quantity' => '25.0000',
        ]);
    });

    it('lists goods receipts with real header fields', function (): void {
        [$token, $companyId, $branchId, $productUnitId, $supplier] = goodsReceiptFixtures();

        foreach (['SJ-100', 'SJ-200'] as $sj) {
            $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
                ->withHeader('X-Branch-Id', (string) $branchId)
                ->postJson('/api/v1/inventory/goods-receipts', [
                    'branch_id' => $branchId,
                    'partner_id' => $supplier->id,
                    'delivery_note_number' => $sj,
                    'receipt_date' => '2026-07-11',
                    'lines' => [
                        ['product_unit_id' => $productUnitId, 'quantity' => 5, 'unit_cost' => 1000],
                    ],
                ])->assertCreated();
        }

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/inventory/goods-receipts')
            ->assertSuccessful()
            ->assertJsonStructure(['data' => ['goods_receipts', 'pagination']])
            ->assertJsonCount(2, 'data.goods_receipts')
            ->assertJsonPath('data.goods_receipts.0.partner.name', $supplier->name)
            ->assertJsonPath('data.goods_receipts.0.item_count', 1)
            ->assertJsonPath('data.goods_receipts.0.status', 'received');
    });

    it('shows a goods receipt with its line items', function (): void {
        [$token, $companyId, $branchId, $productUnitId, $supplier] = goodsReceiptFixtures();

        $receiptId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/goods-receipts', [
                'branch_id' => $branchId,
                'partner_id' => $supplier->id,
                'receipt_date' => '2026-07-12',
                'lines' => [
                    ['product_unit_id' => $productUnitId, 'quantity' => 3, 'unit_cost' => 2000],
                    ['product_unit_id' => $productUnitId, 'quantity' => 7, 'unit_cost' => 2000],
                ],
            ])->assertCreated()->json('data.goods_receipt.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/goods-receipts/{$receiptId}")
            ->assertSuccessful()
            ->assertJsonPath('data.goods_receipt.id', $receiptId)
            ->assertJsonPath('data.goods_receipt.item_count', 2)
            ->assertJsonCount(2, 'data.goods_receipt.lines')
            ->assertJsonPath('data.goods_receipt.lines.0.product_unit_id', $productUnitId)
            ->assertJsonPath('data.goods_receipt.total_cost', '20000.0000');
    });

    it('filters goods receipts by status', function (): void {
        [$token, $companyId, $branchId, $productUnitId, $supplier] = goodsReceiptFixtures();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/goods-receipts', [
                'branch_id' => $branchId,
                'partner_id' => $supplier->id,
                'receipt_date' => '2026-07-13',
                'lines' => [
                    ['product_unit_id' => $productUnitId, 'quantity' => 1, 'unit_cost' => 500],
                ],
            ])->assertCreated();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/inventory/goods-receipts?status=received')
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.goods_receipts');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/inventory/goods-receipts?status=void')
            ->assertSuccessful()
            ->assertJsonCount(0, 'data.goods_receipts');
    });

    it('records a goods receipt line by product_variant_id', function (): void {
        [, $token, $companyId, $branchId] = inventoryActor();
        $uom = createUnit($token, $companyId, 'kg');
        $productId = createProduct($token, $companyId, [
            'name' => 'Gula Pasir',
            'base_uom_id' => $uom,
            'variants' => [['sku' => 'GULA-VAR-1']],
        ]);
        $variantId = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/products/{$productId}")
            ->json('data.product.variants.0.id');
        $supplier = createSupplier($companyId);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/goods-receipts', [
                'branch_id' => $branchId,
                'partner_id' => $supplier->id,
                'receipt_date' => '2026-07-12',
                'lines' => [
                    ['product_variant_id' => $variantId, 'quantity' => 40, 'unit_cost' => 9000],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.goods_receipt.lines.0.product_variant_id', $variantId)
            ->assertJsonPath('data.goods_receipt.total_cost', '360000.0000');

        $this->assertDatabaseHas('stock_movements', [
            'product_variant_id' => $variantId,
            'type' => 'receipt',
            'reference_type' => GoodsReceipt::class,
            'quantity' => '40.0000',
        ]);
    });

    it('rejects a line that supplies neither a product unit nor a product variant', function (): void {
        [$token, $companyId, $branchId, , $supplier] = goodsReceiptFixtures();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/goods-receipts', [
                'branch_id' => $branchId,
                'partner_id' => $supplier->id,
                'receipt_date' => '2026-07-14',
                'lines' => [
                    ['quantity' => 5, 'unit_cost' => 1000],
                ],
            ])
            ->assertStatus(422);
    });

    it('handles exponent-notation numeric input without a 500', function (): void {
        // A tiny JSON float passes the loose `numeric` + gt:0 rules but PHP
        // stringifies it as "1.0E-7", which bcmath rejects. The action must
        // normalize it rather than crash with an unhandled ValueError.
        [$token, $companyId, $branchId, $productUnitId, $supplier] = goodsReceiptFixtures();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/goods-receipts', [
                'branch_id' => $branchId,
                'partner_id' => $supplier->id,
                'receipt_date' => '2026-07-14',
                'lines' => [
                    ['product_unit_id' => $productUnitId, 'quantity' => 0.0000001, 'unit_cost' => 1000],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.goods_receipt.lines.0.line_total', '0.0000');
    });

    it('does not expose goods receipt detail across companies', function (): void {
        // Create the second actor's company up front: creating a company after
        // product fixtures have set the Spatie team id fails in the test harness.
        [, $tokenB, $companyB] = inventoryActor();
        [$tokenA, $companyA, $branchA, $productUnitA, $supplierA] = goodsReceiptFixtures();

        $receiptId = $this->withToken($tokenA)->withHeader('X-Company-Id', (string) $companyA)
            ->withHeader('X-Branch-Id', (string) $branchA)
            ->postJson('/api/v1/inventory/goods-receipts', [
                'branch_id' => $branchA,
                'partner_id' => $supplierA->id,
                'receipt_date' => '2026-07-14',
                'lines' => [
                    ['product_unit_id' => $productUnitA, 'quantity' => 2, 'unit_cost' => 1000],
                ],
            ])->assertCreated()->json('data.goods_receipt.id');

        $this->withToken($tokenB)->withHeader('X-Company-Id', (string) $companyB)
            ->getJson("/api/v1/inventory/goods-receipts/{$receiptId}")
            ->assertForbidden();
    });

    it('rejects a goods receipt with no lines', function (): void {
        [$token, $companyId, $branchId, , $supplier] = goodsReceiptFixtures();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/goods-receipts', [
                'branch_id' => $branchId,
                'partner_id' => $supplier->id,
                'receipt_date' => '2026-07-14',
                'lines' => [],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('lines');
    });

    it('rejects a goods receipt whose branch belongs to another company', function (): void {
        // Second actor first (see note above); index 3 is the primary branch id.
        [, , , $branchB] = inventoryActor();
        [$tokenA, $companyA, , $productUnitA, $supplierA] = goodsReceiptFixtures();

        $this->withToken($tokenA)->withHeader('X-Company-Id', (string) $companyA)
            ->postJson('/api/v1/inventory/goods-receipts', [
                'branch_id' => $branchB,
                'partner_id' => $supplierA->id,
                'receipt_date' => '2026-07-14',
                'lines' => [
                    ['product_unit_id' => $productUnitA, 'quantity' => 2, 'unit_cost' => 1000],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('branch_id');
    });
});
