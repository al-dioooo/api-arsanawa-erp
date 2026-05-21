<?php

use App\Modules\Platform\Services\SettingsManager;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
});

describe('Inventory rewards', function () {
    it('creates a reward with targets', function (): void {
        [, $token, $companyId] = inventoryActor();
        $uom = createUnit($token, $companyId, 'pcs');
        $productId = createProduct($token, $companyId, [
            'name' => 'Coffee', 'base_uom_id' => $uom, 'variants' => [['sku' => 'RWD-1']],
        ]);
        $variantId = test()->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/products/{$productId}")
            ->json('data.product.variants.0.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/inventory/rewards', [
                'name' => 'Loyalty reward',
                'calculation_type' => 'percentage',
                'value' => 5,
                'effective_from' => '2026-01-01',
                'targets' => [['target_type' => 'variant', 'target_id' => $variantId]],
            ])
            ->assertCreated()
            ->assertJsonCount(1, 'data.reward.targets');
    });

    it('lists and deletes rewards', function (): void {
        [, $token, $companyId] = inventoryActor();

        $rewardId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/inventory/rewards', [
                'name' => 'Simple reward',
                'calculation_type' => 'amount',
                'value' => 2000,
                'effective_from' => '2026-01-01',
            ])
            ->assertCreated()
            ->json('data.reward.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/inventory/rewards')
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.rewards');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->deleteJson("/api/v1/inventory/rewards/{$rewardId}")
            ->assertSuccessful();

        $this->assertDatabaseMissing('rewards', ['id' => $rewardId]);
    });

    it('hides discounts when feature flag is disabled', function (): void {
        [, $token, $companyId] = inventoryActor();

        // Disable discounts for the company
        app(SettingsManager::class)->set($companyId, 'inventory', 'discounts_enabled', false);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/inventory/discounts')
            ->assertForbidden();
    });

    it('hides rewards when feature flag is disabled', function (): void {
        [, $token, $companyId] = inventoryActor();

        app(SettingsManager::class)->set($companyId, 'inventory', 'rewards_enabled', false);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/inventory/rewards')
            ->assertForbidden();
    });

    it('allows discounts/rewards when flag is unset (default enabled)', function (): void {
        [, $token, $companyId] = inventoryActor();

        // Flag not set — should default to enabled
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/inventory/rewards')
            ->assertSuccessful();
    });
});
