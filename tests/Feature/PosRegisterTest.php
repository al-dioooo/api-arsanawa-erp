<?php

use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
});

describe('POS registers', function () {
    it('creates, updates, lists, shows, and deletes registers inside the active company', function (): void {
        [, $token, $companyId, $branchId] = financeActor();
        $cashAccountId = createAccount($token, $companyId, [
            'code' => '1-1100',
            'name' => 'Register Cash',
            'type' => 'asset',
        ]);

        $registerId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/pos/registers', [
                'name' => 'Front Desk',
                'code' => 'R1',
                'branch_id' => $branchId,
                'cash_account_id' => $cashAccountId,
            ])
            ->assertCreated()
            ->assertJsonPath('data.register.name', 'Front Desk')
            ->assertJsonPath('data.register.code', 'R1')
            ->assertJsonPath('data.register.branch_id', $branchId)
            ->json('data.register.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->patchJson("/api/v1/pos/registers/{$registerId}", [
                'name' => 'Main Counter',
                'is_active' => false,
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.register.name', 'Main Counter')
            ->assertJsonPath('data.register.is_active', false);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/pos/registers')
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.registers')
            ->assertJsonPath('data.registers.0.id', $registerId);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/pos/registers/{$registerId}")
            ->assertSuccessful()
            ->assertJsonPath('data.register.id', $registerId);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->deleteJson("/api/v1/pos/registers/{$registerId}")
            ->assertSuccessful();

        $this->assertDatabaseMissing('registers', ['id' => $registerId]);
    });

    it('keeps register codes unique per company only', function (): void {
        [, $tokenA, $companyA, $branchA] = financeActor();
        [, $tokenB, $companyB, $branchB] = financeActor();

        $this->withToken($tokenA)->withHeader('X-Company-Id', (string) $companyA)
            ->postJson('/api/v1/pos/registers', [
                'name' => 'Company A Register',
                'code' => 'R1',
                'branch_id' => $branchA,
            ])
            ->assertCreated();

        $this->withToken($tokenA)->withHeader('X-Company-Id', (string) $companyA)
            ->postJson('/api/v1/pos/registers', [
                'name' => 'Duplicate Register',
                'code' => 'R1',
                'branch_id' => $branchA,
            ])
            ->assertUnprocessable();

        $this->withToken($tokenB)->withHeader('X-Company-Id', (string) $companyB)
            ->postJson('/api/v1/pos/registers', [
                'name' => 'Company B Register',
                'code' => 'R1',
                'branch_id' => $branchB,
            ])
            ->assertCreated();

        $this->withToken($tokenB)->withHeader('X-Company-Id', (string) $companyB)
            ->getJson('/api/v1/pos/registers')
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.registers')
            ->assertJsonPath('data.registers.0.name', 'Company B Register');
    });

    it('rejects branches and cash accounts outside the active company', function (): void {
        [, $tokenA, $companyA, $branchA] = financeActor();
        [, $tokenB, $companyB, $branchB] = financeActor();
        $foreignCashAccountId = createAccount($tokenB, $companyB, [
            'code' => '1-1100',
            'name' => 'Foreign Cash',
            'type' => 'asset',
        ]);

        $this->withToken($tokenA)->withHeader('X-Company-Id', (string) $companyA)
            ->postJson('/api/v1/pos/registers', [
                'name' => 'Wrong Branch',
                'code' => 'R1',
                'branch_id' => $branchB,
            ])
            ->assertUnprocessable();

        $this->withToken($tokenA)->withHeader('X-Company-Id', (string) $companyA)
            ->postJson('/api/v1/pos/registers', [
                'name' => 'Wrong Account',
                'code' => 'R2',
                'branch_id' => $branchA,
                'cash_account_id' => $foreignCashAccountId,
            ])
            ->assertUnprocessable();
    });

    it('requires register management permission', function (): void {
        $user = User::factory()->create();
        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.access_token');

        $this->withToken($token)
            ->postJson('/api/v1/pos/registers', [
                'name' => 'Front Desk',
                'code' => 'R1',
                'branch_id' => 1,
            ])
            ->assertForbidden();
    });
});
