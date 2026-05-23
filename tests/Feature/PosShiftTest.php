<?php

use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
});

describe('POS cashier shifts', function () {
    it('opens one shift per register and closes with cash reconciliation', function (): void {
        [, $token, $companyId, $branchId] = financeActor();

        $registerId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/pos/registers', [
                'name' => 'Front Desk',
                'code' => 'R1',
                'branch_id' => $branchId,
            ])
            ->assertCreated()
            ->json('data.register.id');

        $shiftId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/pos/shifts/open', [
                'register_id' => $registerId,
                'opening_float' => 500000,
            ])
            ->assertCreated()
            ->assertJsonPath('data.shift.status', 'open')
            ->assertJsonPath('data.shift.opening_float', '500000.0000')
            ->json('data.shift.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/pos/shifts/open', [
                'register_id' => $registerId,
                'opening_float' => 100000,
            ])
            ->assertUnprocessable();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/pos/shifts/current?register_id={$registerId}")
            ->assertSuccessful()
            ->assertJsonPath('data.shift.id', $shiftId);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/pos/shifts/{$shiftId}/close", [
                'counted_cash' => 500000,
                'notes' => 'Balanced drawer.',
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.shift.status', 'closed')
            ->assertJsonPath('data.shift.expected_cash', '500000.0000')
            ->assertJsonPath('data.shift.cash_variance', '0.0000');
    });

    it('enforces pos operate branch permission when opening a shift', function (): void {
        $user = User::factory()->create();
        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.access_token');

        $this->withToken($token)
            ->postJson('/api/v1/pos/shifts/open', [
                'register_id' => 1,
                'opening_float' => 500000,
            ])
            ->assertForbidden();
    });
});
