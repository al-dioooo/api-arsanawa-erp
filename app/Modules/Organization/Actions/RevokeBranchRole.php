<?php

namespace App\Modules\Organization\Actions;

use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\BranchAssignment;

class RevokeBranchRole
{
    public function execute(Branch $branch, int $userId): void
    {
        BranchAssignment::query()
            ->where('branch_id', $branch->id)
            ->where('user_id', $userId)
            ->delete();
    }
}
