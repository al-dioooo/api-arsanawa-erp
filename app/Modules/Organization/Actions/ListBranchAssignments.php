<?php

namespace App\Modules\Organization\Actions;

use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\BranchAssignment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListBranchAssignments
{
    public function execute(Branch $branch, int $perPage = 25): LengthAwarePaginator
    {
        return BranchAssignment::query()
            ->where('branch_id', $branch->id)
            ->with(['user', 'role'])
            ->orderBy('id')
            ->paginate($perPage);
    }
}
