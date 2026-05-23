<?php

use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Inventory\Models\StockLot;
use Database\Seeders\CurrencySeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
    $this->seed(CurrencySeeder::class);
});

describe('POS sale voiding', function () {
    it('voids a completed sale by reversing journals and restoring stock', function (): void {
        [, $token, $companyId, $branchId] = financeActor();
        $accounts = posPostingAccounts($token, $companyId);
        $registerId = posCompletionRegister($token, $companyId, $branchId, $accounts['cash']);
        $variantId = posCompletionVariant($token, $companyId, 'POS-VOID-1', 50000);
        posStockReceipt($token, $companyId, $branchId, $variantId, 10, 30000);

        $saleId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/pos/sales', [
                'type' => 'counter',
                'branch_id' => $branchId,
                'register_id' => $registerId,
                'order_date' => '2026-05-22',
                'lines' => [['product_variant_id' => $variantId, 'quantity' => 2]],
            ])
            ->assertCreated()
            ->json('data.sale.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/sales/{$saleId}/payments", [
                'method' => 'cash',
                'amount' => 100000,
            ])
            ->assertCreated();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/sales/{$saleId}/complete")
            ->assertSuccessful();

        expect((float) StockLot::query()->where('product_variant_id', $variantId)->sum('remaining_quantity'))->toBe(8.0);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/sales/{$saleId}/void")
            ->assertSuccessful()
            ->assertJsonPath('data.sale.status', 'void');

        expect((float) StockLot::query()->where('product_variant_id', $variantId)->sum('remaining_quantity'))->toBe(10.0);
        expect(JournalEntry::query()->where('reference_id', $saleId)->where('status', 'void')->count())->toBe(2)
            ->and(JournalEntry::query()->where('reference_id', $saleId)->where('entry_number', 'like', 'REV-%')->count())->toBe(2);
    });
});
