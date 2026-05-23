<?php

use Database\Seeders\CurrencySeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
    $this->seed(CurrencySeeder::class);
});

describe('POS reports', function () {
    it('returns a sales summary by type and payment method excluding voided sales', function (): void {
        [, $token, $companyId, $branchId] = financeActor();
        $accounts = posPostingAccounts($token, $companyId);
        $registerId = posCompletionRegister($token, $companyId, $branchId, $accounts['cash']);
        $variantId = posCompletionVariant($token, $companyId, 'POS-REP-1', 100000);
        posStockReceipt($token, $companyId, $branchId, $variantId, 5, 30000);

        $completedSale = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/pos/sales', [
                'type' => 'counter',
                'branch_id' => $branchId,
                'register_id' => $registerId,
                'order_date' => '2026-05-22',
                'lines' => [['product_variant_id' => $variantId, 'quantity' => 1]],
            ])
            ->assertCreated()
            ->json('data.sale.id');

        foreach ([$completedSale] as $saleId) {
            $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
                ->postJson("/api/v1/pos/sales/{$saleId}/payments", ['method' => 'cash', 'amount' => 100000])
                ->assertCreated();

            $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
                ->postJson("/api/v1/pos/sales/{$saleId}/complete")
                ->assertSuccessful();
        }

        $voidedSale = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/pos/sales', [
                'type' => 'counter',
                'branch_id' => $branchId,
                'register_id' => $registerId,
                'order_date' => '2026-05-22',
                'lines' => [['product_variant_id' => $variantId, 'quantity' => 1]],
            ])
            ->assertCreated()
            ->json('data.sale.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/sales/{$voidedSale}/payments", ['method' => 'cash', 'amount' => 100000])
            ->assertCreated();
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/sales/{$voidedSale}/complete")
            ->assertSuccessful();
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/sales/{$voidedSale}/void")
            ->assertSuccessful();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/pos/reports/sales?from=2026-05-01&to=2026-05-31')
            ->assertSuccessful()
            ->assertJsonPath('data.total_sales', '100000.0000')
            ->assertJsonPath('data.by_type.counter', '100000.0000')
            ->assertJsonPath('data.by_payment_method.cash', '100000.0000');
    });

    it('returns a shift report with cash reconciliation', function (): void {
        [, $token, $companyId, $branchId] = financeActor();
        $accounts = posPostingAccounts($token, $companyId);
        $registerId = posCompletionRegister($token, $companyId, $branchId, $accounts['cash']);
        $variantId = posCompletionVariant($token, $companyId, 'POS-REP-2', 111000);
        posStockReceipt($token, $companyId, $branchId, $variantId, 2, 30000);

        $shiftId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/pos/shifts/open', [
                'register_id' => $registerId,
                'opening_float' => 500000,
            ])
            ->assertCreated()
            ->json('data.shift.id');

        $saleId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/pos/sales', [
                'type' => 'counter',
                'branch_id' => $branchId,
                'register_id' => $registerId,
                'order_date' => '2026-05-22',
                'lines' => [['product_variant_id' => $variantId, 'quantity' => 1]],
            ])
            ->assertCreated()
            ->json('data.sale.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/sales/{$saleId}/payments", ['method' => 'cash', 'amount' => 111000])
            ->assertCreated();
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/sales/{$saleId}/complete")
            ->assertSuccessful();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/shifts/{$shiftId}/close", ['counted_cash' => 611000])
            ->assertSuccessful();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/pos/reports/shifts/{$shiftId}")
            ->assertSuccessful()
            ->assertJsonPath('data.opening_float', '500000.0000')
            ->assertJsonPath('data.cash_sales', '111000.0000')
            ->assertJsonPath('data.expected_cash', '611000.0000')
            ->assertJsonPath('data.cash_variance', '0.0000');
    });
});
