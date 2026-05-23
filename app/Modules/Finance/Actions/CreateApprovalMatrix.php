<?php

namespace App\Modules\Finance\Actions;

use App\Models\User;
use App\Modules\Finance\Models\ApprovalMatrix;
use App\Modules\Organization\Models\Membership;
use Illuminate\Validation\ValidationException;

class CreateApprovalMatrix
{
    /**
     * Execute the action.
     *
     * @param  array{document_type: string, min_amount: string, max_amount: string, level: int, approver_user_id: int}  $data
     *
     * @throws ValidationException
     */
    public function execute(int $companyId, User $user, array $data): ApprovalMatrix
    {
        // Enforce that approver is an active member of the company
        $isMember = Membership::query()
            ->where('company_id', $companyId)
            ->where('user_id', $data['approver_user_id'])
            ->where('status', 'active')
            ->exists();

        if (! $isMember) {
            throw ValidationException::withMessages([
                'approver_user_id' => [__('The selected approver must be an active member of the company.')],
            ]);
        }

        // Enforce uniqueness of [company_id, document_type, min_amount, max_amount, level]
        $exists = ApprovalMatrix::query()
            ->where('company_id', $companyId)
            ->where('document_type', $data['document_type'])
            ->where('min_amount', $data['min_amount'])
            ->where('max_amount', $data['max_amount'])
            ->where('level', $data['level'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'level' => [__('An approval rule for this level and amount band already exists.')],
            ]);
        }

        return ApprovalMatrix::create([
            'company_id' => $companyId,
            'document_type' => $data['document_type'],
            'min_amount' => $data['min_amount'],
            'max_amount' => $data['max_amount'],
            'level' => $data['level'],
            'approver_user_id' => $data['approver_user_id'],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }
}
