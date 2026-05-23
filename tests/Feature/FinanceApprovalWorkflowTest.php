<?php

use App\Models\User;
use App\Modules\Finance\Models\ApprovalMatrix;
use App\Modules\Finance\Models\ApprovalRequest;
use App\Modules\Finance\Models\Bill;
use App\Modules\Organization\Models\Membership;
use App\Modules\Partners\Models\Partner;
use Database\Seeders\CurrencySeeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
    $this->seed(CurrencySeeder::class);
});

describe('Finance Document Approval Workflow', function () {
    it('requires approval for bill matching matrix amount band and fails posting', function (): void {
        [$owner, $token, $companyId] = financeActor();

        // Create partner
        $partner = Partner::create([
            'company_id' => $companyId,
            'type' => 'vendor',
            'name' => 'Supplier Inc',
            'code' => 'SUP-001',
            'status' => 'active',
        ]);

        // Setup periods & mappings for bill
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/periods', [
                'name' => 'May 2026',
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-31',
            ])->assertCreated();

        $apAccount = createAccount($token, $companyId, ['code' => '2-1100', 'name' => 'AP', 'type' => 'liability']);
        $expenseAccount = createAccount($token, $companyId, ['code' => '5-1100', 'name' => 'Exp', 'type' => 'expense']);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->putJson('/api/v1/finance/account-mappings', [
                'mappings' => [
                    ['key' => 'accounts_payable', 'account_id' => $apAccount],
                    ['key' => 'purchase_expense', 'account_id' => $expenseAccount],
                ],
            ])->assertSuccessful();

        // Define matrix rule: bill >= 1000 requires Level 1 approval
        $approver = User::factory()->create();
        // Grant finance.approve to approver in the active company
        $approver->assignRole(
            Role::create([
                'name' => 'Approver',
                'company_id' => $companyId,
                'guard_name' => 'api',
            ])->givePermissionTo('finance.approve')
        );
        Membership::create([
            'company_id' => $companyId,
            'user_id' => $approver->id,
            'role' => 'manager',
            'status' => 'active',
        ]);

        // Setup matrix rule: bills between 1000 and 5000 need level 1 approval by $approver
        ApprovalMatrix::create([
            'company_id' => $companyId,
            'document_type' => 'bill',
            'min_amount' => '1000.0000',
            'max_amount' => '5000.0000',
            'level' => 1,
            'approver_user_id' => $approver->id,
        ]);

        // Create a bill with total = 2000 (within range)
        $billId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/bills', [
                'partner_id' => $partner->id,
                'bill_date' => '2026-05-22',
                'due_date' => '2026-06-22',
                'lines' => [
                    [
                        'description' => 'Consulting',
                        'quantity' => '1.0000',
                        'unit_price' => '2000.0000',
                    ],
                ],
            ])->json('data.bill.id');

        // Trying to post the bill directly should fail since it requires approval and no request exists
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/bills/{$billId}/post")
            ->assertUnprocessable();

        // Submit for approval
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/bills/{$billId}/submit-approval")
            ->assertSuccessful();

        // Trying to post now should still fail because approval request is pending
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/bills/{$billId}/post")
            ->assertUnprocessable();

        // Approver acts on the approval
        $request = ApprovalRequest::where('approvable_id', $billId)->first();

        $approverToken = test()->postJson('/api/v1/auth/login', [
            'login' => $approver->email,
            'password' => 'password',
        ])->json('data.access_token');

        $this->withToken($approverToken)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/approval-requests/{$request->id}/act", [
                'action' => 'approved',
                'remark' => 'Looks good',
            ])->assertSuccessful();

        // Now post should succeed
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/bills/{$billId}/post")
            ->assertSuccessful();
    });

    it('requires multiple sequential approvals and handles rejects', function (): void {
        [$owner, $token, $companyId] = financeActor();

        $partner = Partner::create([
            'company_id' => $companyId,
            'type' => 'vendor',
            'name' => 'Supplier Inc',
            'code' => 'SUP-001',
            'status' => 'active',
        ]);

        // Setup periods & mappings
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/periods', [
                'name' => 'May 2026',
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-31',
            ])->assertCreated();

        $apAccount = createAccount($token, $companyId, ['code' => '2-1100', 'name' => 'AP', 'type' => 'liability']);
        $expenseAccount = createAccount($token, $companyId, ['code' => '5-1100', 'name' => 'Exp', 'type' => 'expense']);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->putJson('/api/v1/finance/account-mappings', [
                'mappings' => [
                    ['key' => 'accounts_payable', 'account_id' => $apAccount],
                    ['key' => 'purchase_expense', 'account_id' => $expenseAccount],
                ],
            ])->assertSuccessful();

        // Create 2 approvers
        $approver1 = User::factory()->create();
        $approver2 = User::factory()->create();

        foreach ([$approver1, $approver2] as $app) {
            $app->assignRole(
                Role::create([
                    'name' => 'Role '.$app->id,
                    'company_id' => $companyId,
                    'guard_name' => 'api',
                ])->givePermissionTo('finance.approve')
            );
            Membership::create([
                'company_id' => $companyId,
                'user_id' => $app->id,
                'role' => 'manager',
                'status' => 'active',
            ]);
        }

        // Setup sequential matrix rules:
        // Level 1: $approver1
        ApprovalMatrix::create([
            'company_id' => $companyId,
            'document_type' => 'bill',
            'min_amount' => '1000.0000',
            'max_amount' => '5000.0000',
            'level' => 1,
            'approver_user_id' => $approver1->id,
        ]);
        // Level 2: $approver2
        ApprovalMatrix::create([
            'company_id' => $companyId,
            'document_type' => 'bill',
            'min_amount' => '1000.0000',
            'max_amount' => '5000.0000',
            'level' => 2,
            'approver_user_id' => $approver2->id,
        ]);

        // Create bill
        $billId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/bills', [
                'partner_id' => $partner->id,
                'bill_date' => '2026-05-22',
                'due_date' => '2026-06-22',
                'lines' => [['description' => 'Consulting', 'quantity' => '1.0000', 'unit_price' => '2000.0000']],
            ])->json('data.bill.id');

        // Submit
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/bills/{$billId}/submit-approval")
            ->assertSuccessful();

        $request = ApprovalRequest::where('approvable_id', $billId)->first();
        expect($request->current_level)->toBe(1);

        $token1 = test()->postJson('/api/v1/auth/login', ['login' => $approver1->email, 'password' => 'password'])->json('data.access_token');
        $token2 = test()->postJson('/api/v1/auth/login', ['login' => $approver2->email, 'password' => 'password'])->json('data.access_token');

        // Level 2 approver cannot approve level 1
        $this->withToken($token2)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/approval-requests/{$request->id}/act", [
                'action' => 'approved',
            ])->assertUnprocessable();

        // Level 1 approves
        $this->withToken($token1)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/approval-requests/{$request->id}/act", [
                'action' => 'approved',
            ])->assertSuccessful();

        $request->refresh();
        expect($request->current_level)->toBe(2);
        expect($request->status)->toBe('pending');

        // Reject by Level 2
        $this->withToken($token2)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/approval-requests/{$request->id}/act", [
                'action' => 'rejected',
                'remark' => 'Over budget',
            ])->assertSuccessful();

        $request->refresh();
        expect($request->status)->toBe('rejected');

        // Try posting - fails
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/bills/{$billId}/post")
            ->assertUnprocessable();
    });

    it('resets approval request when a draft document is modified', function (): void {
        [$owner, $token, $companyId] = financeActor();

        $partner = Partner::create([
            'company_id' => $companyId,
            'type' => 'vendor',
            'name' => 'Supplier Inc',
            'code' => 'SUP-001',
            'status' => 'active',
        ]);

        $approver = User::factory()->create();
        Membership::create([
            'company_id' => $companyId,
            'user_id' => $approver->id,
            'role' => 'manager',
            'status' => 'active',
        ]);

        ApprovalMatrix::create([
            'company_id' => $companyId,
            'document_type' => 'bill',
            'min_amount' => '1000.0000',
            'max_amount' => '5000.0000',
            'level' => 1,
            'approver_user_id' => $approver->id,
        ]);

        // Create bill
        $billId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/bills', [
                'partner_id' => $partner->id,
                'bill_date' => '2026-05-22',
                'due_date' => '2026-06-22',
                'lines' => [['description' => 'Consulting', 'quantity' => '1.0000', 'unit_price' => '2000.0000']],
            ])->json('data.bill.id');

        // Submit for approval
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/bills/{$billId}/submit-approval")
            ->assertSuccessful();

        expect(ApprovalRequest::where('approvable_id', $billId)->exists())->toBeTrue();

        // Update the bill - should delete/reset approval request
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->patchJson("/api/v1/finance/bills/{$billId}", [
                'notes' => 'Updated notes',
            ])->assertSuccessful();

        expect(ApprovalRequest::where('approvable_id', $billId)->exists())->toBeFalse();
    });

    it('skips approval and posts directly if no matrix rules match', function (): void {
        [$owner, $token, $companyId] = financeActor();

        $partner = Partner::create([
            'company_id' => $companyId,
            'type' => 'vendor',
            'name' => 'Supplier Inc',
            'code' => 'SUP-001',
            'status' => 'active',
        ]);

        // Setup periods & mappings
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/periods', [
                'name' => 'May 2026',
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-31',
            ])->assertCreated();

        $apAccount = createAccount($token, $companyId, ['code' => '2-1100', 'name' => 'AP', 'type' => 'liability']);
        $expenseAccount = createAccount($token, $companyId, ['code' => '5-1100', 'name' => 'Exp', 'type' => 'expense']);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->putJson('/api/v1/finance/account-mappings', [
                'mappings' => [
                    ['key' => 'accounts_payable', 'account_id' => $apAccount],
                    ['key' => 'purchase_expense', 'account_id' => $expenseAccount],
                ],
            ])->assertSuccessful();

        // Create a small bill total = 100. (No rules exist for 100)
        $billId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/bills', [
                'partner_id' => $partner->id,
                'bill_date' => '2026-05-22',
                'due_date' => '2026-06-22',
                'lines' => [['description' => 'Consulting', 'quantity' => '1.0000', 'unit_price' => '100.0000']],
            ])->json('data.bill.id');

        // Post directly - should succeed
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/bills/{$billId}/post")
            ->assertSuccessful();
    });
});
