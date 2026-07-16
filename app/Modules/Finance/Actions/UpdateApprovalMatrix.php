<?php

namespace App\Modules\Finance\Actions;

use App\Models\User;
use App\Modules\Finance\Models\ApprovalMatrix;
use App\Modules\Organization\Actions\CheckActiveMembership;
use Illuminate\Validation\ValidationException;

class UpdateApprovalMatrix
{
    public function __construct(private readonly CheckActiveMembership $activeMembership) {}

    /**
     * Execute the action.
     *
     * @param  array{document_type?: string, min_amount?: string, max_amount?: string, level?: int, approver_user_id?: int}  $data
     *
     * @throws ValidationException
     */
    public function execute(ApprovalMatrix $matrix, User $user, array $data): ApprovalMatrix
    {
        if (isset($data['approver_user_id']) && $data['approver_user_id'] !== $matrix->approver_user_id) {
            $isMember = $this->activeMembership->execute($matrix->company_id, $data['approver_user_id']);

            if (! $isMember) {
                throw ValidationException::withMessages([
                    'approver_user_id' => [__('The selected approver must be an active member of the company.')],
                ]);
            }
        }

        // Validate uniqueness if any unique fields are changing
        $docType = $data['document_type'] ?? $matrix->document_type;
        $minAmt = $data['min_amount'] ?? $matrix->min_amount;
        $maxAmt = $data['max_amount'] ?? $matrix->max_amount;
        $level = $data['level'] ?? $matrix->level;

        if (
            $docType !== $matrix->document_type ||
            $minAmt !== $matrix->min_amount ||
            $maxAmt !== $matrix->max_amount ||
            $level !== $matrix->level
        ) {
            $exists = ApprovalMatrix::query()
                ->where('company_id', $matrix->company_id)
                ->where('document_type', $docType)
                ->where('min_amount', $minAmt)
                ->where('max_amount', $maxAmt)
                ->where('level', $level)
                ->where('id', '!=', $matrix->id)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'level' => [__('An approval rule for this level and amount band already exists.')],
                ]);
            }
        }

        $matrix->fill(array_filter([
            'document_type' => $data['document_type'] ?? null,
            'min_amount' => $data['min_amount'] ?? null,
            'max_amount' => $data['max_amount'] ?? null,
            'level' => $data['level'] ?? null,
            'approver_user_id' => $data['approver_user_id'] ?? null,
        ], fn ($v) => $v !== null));

        $matrix->updated_by = $user->id;
        $matrix->save();

        return $matrix;
    }
}
