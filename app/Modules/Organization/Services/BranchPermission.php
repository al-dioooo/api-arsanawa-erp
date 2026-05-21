<?php

namespace App\Modules\Organization\Services;

use App\Models\User;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\BranchAssignment;

class BranchPermission
{
    /**
     * Per-request memo of resolved checks.
     *
     * @var array<string, bool>
     */
    private array $memo = [];

    /**
     * Whether the user holds a permission in a specific branch. A company-wide
     * grant always wins; otherwise the role assigned for that branch is consulted.
     */
    public function userCanInBranch(User $user, string $permission, int $branchId): bool
    {
        $key = "{$user->id}:{$permission}:{$branchId}";

        return $this->memo[$key] ??= $this->resolve($user, $permission, $branchId);
    }

    private function resolve(User $user, string $permission, int $branchId): bool
    {
        $branch = Branch::find($branchId);

        if ($branch === null) {
            return false;
        }

        if ($this->hasCompanyWideGrant($user, $permission, $branch->company_id)) {
            return true;
        }

        $assignment = BranchAssignment::query()
            ->where('branch_id', $branchId)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->with('role.permissions')
            ->first();

        if ($assignment?->role === null) {
            return false;
        }

        return $assignment->role->permissions->contains('name', $permission);
    }

    private function hasCompanyWideGrant(User $user, string $permission, int $companyId): bool
    {
        $previousTeamId = getPermissionsTeamId();
        setPermissionsTeamId($companyId);

        try {
            return $user->can($permission);
        } finally {
            setPermissionsTeamId($previousTeamId);
        }
    }
}
