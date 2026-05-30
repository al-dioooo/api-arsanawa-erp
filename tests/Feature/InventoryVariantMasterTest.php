<?php

use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
});

describe('Inventory variants master data', function () {
    it('creates, lists, shows, updates and deletes a variant', function (): void {
        [, $token, $companyId] = inventoryActor();
        $groupId = createVariantGroup($token, $companyId, 'Protein', 'protein');

        $variantId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/inventory/variants', [
                'variant_group_id' => $groupId,
                'name' => 'Ayam',
                'code' => 'ayam',
                'position' => 10,
            ])
            ->assertCreated()
            ->assertJsonPath('data.variant.name', 'Ayam')
            ->assertJsonPath('data.variant.group.id', $groupId)
            ->json('data.variant.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/variants?variant_group_id={$groupId}")
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.variants')
            ->assertJsonPath('data.variants.0.code', 'ayam');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/inventory/variants/{$variantId}")
            ->assertSuccessful()
            ->assertJsonPath('data.variant.name', 'Ayam');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->patchJson("/api/v1/inventory/variants/{$variantId}", [
                'name' => 'Ayam Panggang',
                'is_active' => false,
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.variant.name', 'Ayam Panggang')
            ->assertJsonPath('data.variant.is_active', false);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->deleteJson("/api/v1/inventory/variants/{$variantId}")
            ->assertSuccessful();

        $this->assertDatabaseMissing('variants', ['id' => $variantId]);
    });

    it('rejects duplicate variant codes within a group', function (): void {
        [, $token, $companyId] = inventoryActor();
        $groupId = createVariantGroup($token, $companyId, 'Spice Level', 'spice');

        $payload = [
            'variant_group_id' => $groupId,
            'name' => 'Spicy',
            'code' => 'spicy',
        ];

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/inventory/variants', $payload)
            ->assertCreated();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/inventory/variants', $payload)
            ->assertUnprocessable();
    });

    it('returns 404 for a variant belonging to another company', function (): void {
        [, $tokenA, $companyA] = inventoryActor();
        [, $tokenB, $companyB] = inventoryActor();
        $groupId = createVariantGroup($tokenA, $companyA, 'Rice Type', 'rice');

        $variantId = $this->withToken($tokenA)->withHeader('X-Company-Id', (string) $companyA)
            ->postJson('/api/v1/inventory/variants', [
                'variant_group_id' => $groupId,
                'name' => 'Nasi Liwet',
                'code' => 'liwet',
            ])
            ->json('data.variant.id');

        $this->withToken($tokenB)->withHeader('X-Company-Id', (string) $companyB)
            ->getJson("/api/v1/inventory/variants/{$variantId}")
            ->assertNotFound();
    });
});
