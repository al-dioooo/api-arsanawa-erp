<?php

use App\Modules\Partners\Models\Partner;
use Database\Seeders\CurrencySeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
    $this->seed(CurrencySeeder::class);
});

describe('POS catering orders', function () {
    it('requires partner and fulfilment date for catering sales', function (): void {
        [, $token, $companyId, $branchId] = financeActor();
        $accounts = posPostingAccounts($token, $companyId);
        $registerId = posCompletionRegister($token, $companyId, $branchId, $accounts['cash']);
        $variantId = posCompletionVariant($token, $companyId, 'POS-CAT-1', 100000);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/pos/sales', [
                'type' => 'catering',
                'branch_id' => $branchId,
                'register_id' => $registerId,
                'lines' => [['product_variant_id' => $variantId, 'quantity' => 1]],
            ])
            ->assertUnprocessable();
    });

    it('confirms a catering sale before completion and filters by fulfilment date', function (): void {
        [, $token, $companyId, $branchId] = financeActor();
        $accounts = posPostingAccounts($token, $companyId);
        $registerId = posCompletionRegister($token, $companyId, $branchId, $accounts['cash']);
        $variantId = posCompletionVariant($token, $companyId, 'POS-CAT-2', 100000);
        posStockReceipt($token, $companyId, $branchId, $variantId, 2, 40000);
        $partner = Partner::create([
            'company_id' => $companyId,
            'type' => 'customer',
            'name' => 'Catering Customer',
            'code' => 'CAT-002',
            'status' => 'active',
        ]);

        $saleId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/pos/sales', [
                'type' => 'catering',
                'branch_id' => $branchId,
                'register_id' => $registerId,
                'partner_id' => $partner->id,
                'order_date' => '2026-05-22',
                'fulfilment_date' => '2026-05-25',
                'lines' => [['product_variant_id' => $variantId, 'quantity' => 1]],
            ])
            ->assertCreated()
            ->json('data.sale.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/sales/{$saleId}/complete")
            ->assertUnprocessable();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/sales/{$saleId}/confirm")
            ->assertSuccessful()
            ->assertJsonPath('data.sale.status', 'confirmed');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/pos/sales?type=catering&fulfilment_from=2026-05-24&fulfilment_to=2026-05-26')
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.sales')
            ->assertJsonPath('data.sales.0.id', $saleId);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/sales/{$saleId}/complete")
            ->assertSuccessful()
            ->assertJsonPath('data.sale.status', 'completed');
    });
});
