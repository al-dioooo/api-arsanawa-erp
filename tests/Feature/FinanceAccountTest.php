<?php

use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
});

describe('Finance chart of accounts', function () {
    it('creates an account and returns it', function (): void {
        [, $token, $companyId] = financeActor();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/accounts', [
                'code' => '1-0000',
                'name' => 'Assets',
                'type' => 'asset',
            ])
            ->assertCreated()
            ->assertJsonPath('data.account.code', '1-0000')
            ->assertJsonPath('data.account.type', 'asset')
            ->assertJsonPath('data.account.normal_balance', 'debit')
            ->assertJsonPath('data.account.depth', 0)
            ->assertJsonPath('data.account.is_postable', true);
    });

    it('nests a child under a parent and sets depth, marking parent non-postable', function (): void {
        [, $token, $companyId] = financeActor();

        $parentId = createAccount($token, $companyId, [
            'code' => '1-0000',
            'name' => 'Assets',
            'type' => 'asset',
        ]);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/accounts', [
                'code' => '1-1000',
                'name' => 'Cash',
                'type' => 'asset',
                'parent_id' => $parentId,
            ])
            ->assertCreated()
            ->assertJsonPath('data.account.depth', 1)
            ->assertJsonPath('data.account.is_postable', true);

        // Parent should now be non-postable
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/finance/accounts/{$parentId}")
            ->assertJsonPath('data.account.is_postable', false);
    });

    it('rejects duplicate code within the same company', function (): void {
        [, $token, $companyId] = financeActor();

        createAccount($token, $companyId, [
            'code' => '1-0000',
            'name' => 'Assets',
            'type' => 'asset',
        ]);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/accounts', [
                'code' => '1-0000',
                'name' => 'Assets Duplicate',
                'type' => 'asset',
            ])
            ->assertUnprocessable();
    });

    it('creates accounts for all five types', function (): void {
        [, $token, $companyId] = financeActor();

        foreach (['asset', 'liability', 'equity', 'revenue', 'expense'] as $i => $type) {
            $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
                ->postJson('/api/v1/finance/accounts', [
                    'code' => ($i + 1).'-0000',
                    'name' => ucfirst($type),
                    'type' => $type,
                ])
                ->assertCreated()
                ->assertJsonPath('data.account.type', $type);
        }
    });

    it('isolates accounts between companies', function (): void {
        [, $tokenA, $companyA] = financeActor();
        [, $tokenB, $companyB] = financeActor();

        $accountId = createAccount($tokenA, $companyA, [
            'code' => '1-0000',
            'name' => 'Assets',
            'type' => 'asset',
        ]);

        // Company B cannot see company A's account
        $this->withToken($tokenB)->withHeader('X-Company-Id', (string) $companyB)
            ->getJson("/api/v1/finance/accounts/{$accountId}")
            ->assertNotFound();
    });

    it('rejects account creation without finance.manage-accounts permission', function (): void {
        $user = User::factory()->create();
        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.access_token');

        // The user has no company, so no permissions
        $this->withToken($token)
            ->postJson('/api/v1/finance/accounts', [
                'code' => '1-0000',
                'name' => 'Assets',
                'type' => 'asset',
            ])
            ->assertForbidden();
    });

    it('deletes a leaf account but rejects deleting an account with children', function (): void {
        [, $token, $companyId] = financeActor();

        $parentId = createAccount($token, $companyId, [
            'code' => '1-0000',
            'name' => 'Assets',
            'type' => 'asset',
        ]);

        $childId = createAccount($token, $companyId, [
            'code' => '1-1000',
            'name' => 'Cash',
            'type' => 'asset',
            'parent_id' => $parentId,
        ]);

        // Cannot delete parent with children
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->deleteJson("/api/v1/finance/accounts/{$parentId}")
            ->assertUnprocessable();

        // Can delete leaf
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->deleteJson("/api/v1/finance/accounts/{$childId}")
            ->assertSuccessful();
    });

    it('lists accounts ordered by code', function (): void {
        [, $token, $companyId] = financeActor();

        createAccount($token, $companyId, ['code' => '2-0000', 'name' => 'Liabilities', 'type' => 'liability']);
        createAccount($token, $companyId, ['code' => '1-0000', 'name' => 'Assets', 'type' => 'asset']);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/finance/accounts')
            ->assertSuccessful()
            ->assertJsonCount(2, 'data.accounts')
            ->assertJsonPath('data.accounts.0.code', '1-0000')
            ->assertJsonPath('data.accounts.1.code', '2-0000');
    });
});
