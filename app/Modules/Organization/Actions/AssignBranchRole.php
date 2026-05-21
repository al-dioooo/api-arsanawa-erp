<?php

namespace App\Modules\Organization\Actions;

use App\Models\User;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\BranchAssignment;
use App\Modules\Organization\Models\Company;

class AssignBranchRole
{
    /**
     * Create or update a user's role on a branch.
     *
     * @param  array{user_id: int, role_id: int, status?: string}  $data
     */
    public function execute(Company $company, Branch $branch, User $actor, array $data): BranchAssignment
    {
        $assignment = BranchAssignment::firstOrNew([
            'branch_id' => $branch->id,
            'user_id' => $data['user_id'],
        ]);

        if (! $assignment->exists) {
            $assignment->created_by = $actor->id;
        }

        $assignment->company_id = $company->id;
        $assignment->role_id = $data['role_id'];
        $assignment->status = $data['status'] ?? 'active';
        $assignment->updated_by = $actor->id;
        $assignment->save();

        return $assignment->load(['user', 'role']);
    }
}
