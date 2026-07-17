<?php

use App\Modules\Inventory\Models\StockLot;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\StockService;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
});

describe('Inventory stock issues and adjustments', function () {
    it('issues and adjusts stock by product unit sellable SKU', function (): void {
        [, $token, $companyId, $branchId] = inventoryActor();
        $uom = createUnit($token, $companyId, 'pcs');
        $productId = createProduct($token, $companyId, [
            'name' => 'Snack Box',
            'base_uom_id' => $uom,
        ]);
        $productUnitId = createProductUnit($token, $companyId, $productId, 'SKL-SNB-REG');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/stock/receipts', [
                'product_unit_id' => $productUnitId,
                'branch_id' => $branchId,
                'quantity' => 12,
                'unit_cost' => 18000,
            ])->assertCreated();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/stock/issues', [
                'product_unit_id' => $productUnitId,
                'branch_id' => $branchId,
                'quantity' => 3,
                'notes' => 'Kitchen usage',
            ])->assertSuccessful();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/stock/adjustments', [
                'product_unit_id' => $productUnitId,
                'branch_id' => $branchId,
                'quantity' => -2,
                'notes' => 'Stock count correction',
            ])->assertSuccessful();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/stock/levels?product_unit_id={$productUnitId}&branch_id={$branchId}")
            ->assertSuccessful()
            ->assertJsonPath('data.on_hand', '7.0000');

        $this->assertDatabaseHas('stock_movements', [
            'product_unit_id' => $productUnitId,
            'branch_id' => $branchId,
            'type' => 'issue',
            'quantity' => '-3.0000',
        ]);
    });

    it('rejects product unit over-issue without changing stock', function (): void {
        [, $token, $companyId, $branchId] = inventoryActor();
        $uom = createUnit($token, $companyId, 'pcs');
        $productId = createProduct($token, $companyId, [
            'name' => 'Bottled Water',
            'base_uom_id' => $uom,
        ]);
        $productUnitId = createProductUnit($token, $companyId, $productId, 'SKL-AIR-600');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/stock/receipts', [
                'product_unit_id' => $productUnitId,
                'branch_id' => $branchId,
                'quantity' => 4,
                'unit_cost' => 2500,
            ])->assertCreated();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/stock/issues', [
                'product_unit_id' => $productUnitId,
                'branch_id' => $branchId,
                'quantity' => 5,
            ])->assertUnprocessable();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/stock/levels?product_unit_id={$productUnitId}&branch_id={$branchId}")
            ->assertSuccessful()
            ->assertJsonPath('data.on_hand', '4.0000');
    });

    it('issues stock FIFO across multiple lots', function (): void {
        [, $token, $companyId, $branchId] = inventoryActor();
        $uom = createUnit($token, $companyId, 'pcs');
        $productId = createProduct($token, $companyId, [
            'name' => 'Coffee',
            'base_uom_id' => $uom,
            'variants' => [['sku' => 'ISS-1']],
        ]);
        $variantId = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/products/{$productId}")
            ->json('data.product.variants.0.id');

        // Receipt 1 — older, cost 1000
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/stock/receipts', [
                'product_variant_id' => $variantId,
                'branch_id' => $branchId,
                'quantity' => 10,
                'unit_cost' => 1000,
                'received_at' => '2026-01-01',
            ])->assertCreated();

        // Receipt 2 — newer, cost 1200
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/stock/receipts', [
                'product_variant_id' => $variantId,
                'branch_id' => $branchId,
                'quantity' => 10,
                'unit_cost' => 1200,
                'received_at' => '2026-02-01',
            ])->assertCreated();

        // Issue 15 — should consume all of lot 1 (10 @ 1000) + 5 of lot 2 (@ 1200)
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/stock/issues', [
                'product_variant_id' => $variantId,
                'branch_id' => $branchId,
                'quantity' => 15,
            ])->assertSuccessful();

        // Check on-hand
        $response = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/stock/levels?product_variant_id={$variantId}&branch_id={$branchId}");

        $response->assertSuccessful()
            ->assertJsonPath('data.on_hand', '5.0000');

        // Lot 1 should be depleted
        $this->assertDatabaseHas('stock_lots', [
            'product_variant_id' => $variantId,
            'branch_id' => $branchId,
            'unit_cost' => '1000.0000',
            'status' => 'depleted',
        ]);
    });

    it('issues stock FIFO across more lots than one fetch chunk', function (): void {
        [, $token, $companyId, $branchId] = inventoryActor();
        $uom = createUnit($token, $companyId, 'pcs');
        $productId = createProduct($token, $companyId, [
            'name' => 'Bulk Beans',
            'base_uom_id' => $uom,
            'variants' => [['sku' => 'CHUNK-1']],
        ]);
        $variantId = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/products/{$productId}")
            ->json('data.product.variants.0.id');

        $lotCount = StockService::LOT_CHUNK_SIZE + 2;
        foreach (range(1, $lotCount) as $index) {
            StockLot::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'product_variant_id' => $variantId,
                'received_quantity' => 1,
                'remaining_quantity' => 1,
                'unit_cost' => 1000,
                'received_at' => '2026-01-01',
                'status' => 'active',
            ]);
        }

        app(StockService::class)->recordIssue([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'product_variant_id' => $variantId,
            'quantity' => $lotCount - 1,
        ]);

        expect(StockLot::query()
            ->where('product_variant_id', $variantId)
            ->where('status', 'active')
            ->count())->toBe(1);
        expect(StockMovement::query()
            ->where('product_variant_id', $variantId)
            ->where('type', 'issue')
            ->count())->toBe($lotCount - 1);
    });

    it('rejects over-issue', function (): void {
        [, $token, $companyId, $branchId] = inventoryActor();
        $uom = createUnit($token, $companyId, 'pcs');
        $productId = createProduct($token, $companyId, [
            'name' => 'Sugar',
            'base_uom_id' => $uom,
            'variants' => [['sku' => 'OI-1']],
        ]);
        $variantId = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/products/{$productId}")
            ->json('data.product.variants.0.id');

        // Receipt 5 units
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/stock/receipts', [
                'product_variant_id' => $variantId,
                'branch_id' => $branchId,
                'quantity' => 5,
                'unit_cost' => 2000,
            ])->assertCreated();

        // Issue 6 — should be rejected
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/stock/issues', [
                'product_variant_id' => $variantId,
                'branch_id' => $branchId,
                'quantity' => 6,
            ])->assertUnprocessable();

        // On-hand unchanged
        $response = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/stock/levels?product_variant_id={$variantId}&branch_id={$branchId}");

        $response->assertJsonPath('data.on_hand', '5.0000');
    });

    it('adjusts stock upward (creates a lot)', function (): void {
        [, $token, $companyId, $branchId] = inventoryActor();
        $uom = createUnit($token, $companyId, 'pcs');
        $productId = createProduct($token, $companyId, [
            'name' => 'Flour',
            'base_uom_id' => $uom,
            'variants' => [['sku' => 'ADJ-UP']],
        ]);
        $variantId = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/products/{$productId}")
            ->json('data.product.variants.0.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/stock/adjustments', [
                'product_variant_id' => $variantId,
                'branch_id' => $branchId,
                'quantity' => 20,
                'unit_cost' => 3000,
            ])->assertSuccessful();

        $response = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/stock/levels?product_variant_id={$variantId}&branch_id={$branchId}");

        $response->assertJsonPath('data.on_hand', '20.0000');
    });

    it('adjusts stock downward (consumes FIFO)', function (): void {
        [, $token, $companyId, $branchId] = inventoryActor();
        $uom = createUnit($token, $companyId, 'pcs');
        $productId = createProduct($token, $companyId, [
            'name' => 'Butter',
            'base_uom_id' => $uom,
            'variants' => [['sku' => 'ADJ-DN']],
        ]);
        $variantId = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/products/{$productId}")
            ->json('data.product.variants.0.id');

        // Receipt first
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/stock/receipts', [
                'product_variant_id' => $variantId,
                'branch_id' => $branchId,
                'quantity' => 30,
                'unit_cost' => 4000,
            ])->assertCreated();

        // Adjust down by 10
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->postJson('/api/v1/inventory/stock/adjustments', [
                'product_variant_id' => $variantId,
                'branch_id' => $branchId,
                'quantity' => -10,
            ])->assertSuccessful();

        $response = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/stock/levels?product_variant_id={$variantId}&branch_id={$branchId}");

        $response->assertJsonPath('data.on_hand', '20.0000');
    });
});
