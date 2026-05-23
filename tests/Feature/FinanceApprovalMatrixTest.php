<?php

use App\Models\User;
use App\Modules\Finance\Models\ApprovalMatrix;
use App\Modules\Organization\Models\Membership;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
});

describe('Finance Approval Matrix CRUD and Validation', function () {
    it('creates an approval matrix rule when user is a company member', function (): void {
        [$owner, $token, $companyId] = financeActor();

        // Create another user to be the approver and make them a member of the company
        $approver = User::factory()->create();
        Membership::create([
            'company_id' => $companyId,
            'user_id' => $approver->id,
            'role' => 'manager',
            'status' => 'active',
        ]);

        $ruleData = [
            'document_type' => 'bill',
            'min_amount' => '1000.0000',
            'max_amount' => '5000.0000',
            'level' => 1,
            'approver_user_id' => $approver->id,
        ];

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/approval-matrices', $ruleData)
            ->assertCreated()
            ->assertJsonPath('data.approval_matrix.document_type', 'bill')
            ->assertJsonPath('data.approval_matrix.min_amount', '1000.0000')
            ->assertJsonPath('data.approval_matrix.max_amount', '5000.0000')
            ->assertJsonPath('data.approval_matrix.level', 1)
            ->assertJsonPath('data.approval_matrix.approver_user_id', $approver->id);
    });

    it('rejects creation if the approver is not a member of the company', function (): void {
        [$owner, $token, $companyId] = financeActor();

        $nonMember = User::factory()->create();

        $ruleData = [
            'document_type' => 'bill',
            'min_amount' => '1000.0000',
            'max_amount' => '5000.0000',
            'level' => 1,
            'approver_user_id' => $nonMember->id,
        ];

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/approval-matrices', $ruleData)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('approver_user_id');
    });

    it('rejects creation of duplicate levels for the same band', function (): void {
        [$owner, $token, $companyId] = financeActor();

        $approver1 = User::factory()->create();
        Membership::create([
            'company_id' => $companyId,
            'user_id' => $approver1->id,
            'role' => 'manager',
            'status' => 'active',
        ]);

        $ruleData = [
            'document_type' => 'bill',
            'min_amount' => '1000.0000',
            'max_amount' => '5000.0000',
            'level' => 1,
            'approver_user_id' => $approver1->id,
        ];

        // Create first rule
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/approval-matrices', $ruleData)
            ->assertCreated();

        // Try creating duplicate level on same band
        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/approval-matrices', $ruleData)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('level');
    });

    it('updates an existing approval matrix rule', function (): void {
        [$owner, $token, $companyId] = financeActor();

        $approver = User::factory()->create();
        Membership::create([
            'company_id' => $companyId,
            'user_id' => $approver->id,
            'role' => 'manager',
            'status' => 'active',
        ]);

        $matrix = ApprovalMatrix::create([
            'company_id' => $companyId,
            'document_type' => 'bill',
            'min_amount' => '1000.0000',
            'max_amount' => '5000.0000',
            'level' => 1,
            'approver_user_id' => $approver->id,
        ]);

        $anotherApprover = User::factory()->create();
        Membership::create([
            'company_id' => $companyId,
            'user_id' => $anotherApprover->id,
            'role' => 'manager',
            'status' => 'active',
        ]);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->patchJson("/api/v1/finance/approval-matrices/{$matrix->id}", [
                'approver_user_id' => $anotherApprover->id,
                'min_amount' => '2000.0000',
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.approval_matrix.approver_user_id', $anotherApprover->id)
            ->assertJsonPath('data.approval_matrix.min_amount', '2000.0000');
    });

    it('deletes an approval matrix rule', function (): void {
        [$owner, $token, $companyId] = financeActor();

        $approver = User::factory()->create();
        $matrix = ApprovalMatrix::create([
            'company_id' => $companyId,
            'document_type' => 'bill',
            'min_amount' => '1000.0000',
            'max_amount' => '5000.0000',
            'level' => 1,
            'approver_user_id' => $approver->id,
        ]);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->deleteJson("/api/v1/finance/approval-matrices/{$matrix->id}")
            ->assertSuccessful();

        expect(ApprovalMatrix::find($matrix->id))->toBeNull();
    });

    it('lists approval matrix rules for a company', function (): void {
        [$owner, $token, $companyId] = financeActor();

        $approver = User::factory()->create();
        ApprovalMatrix::create([
            'company_id' => $companyId,
            'document_type' => 'bill',
            'min_amount' => '1000.0000',
            'max_amount' => '5000.0000',
            'level' => 1,
            'approver_user_id' => $approver->id,
        ]);

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/finance/approval-matrices')
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.approval_matrices');
    });
});
