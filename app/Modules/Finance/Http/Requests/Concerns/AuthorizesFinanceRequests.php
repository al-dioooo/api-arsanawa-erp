<?php

namespace App\Modules\Finance\Http\Requests\Concerns;

use App\Modules\Organization\Services\BranchPermission;

trait AuthorizesFinanceRequests
{
    protected function activeCompanyId(): ?int
    {
        $id = $this->attributes->get('active_company_id');

        return $id !== null ? (int) $id : null;
    }

    protected function activeBranchId(): ?int
    {
        $id = $this->attributes->get('active_branch_id');

        return $id !== null ? (int) $id : null;
    }

    protected function canInActiveCompany(string $permission): bool
    {
        $companyId = $this->activeCompanyId();

        if ($companyId === null) {
            return false;
        }

        setPermissionsTeamId($companyId);

        return $this->user()?->can($permission) ?? false;
    }

    protected function canInBranch(string $permission, int $branchId): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return app(BranchPermission::class)->userCanInBranch($user, $permission, $branchId);
    }
}
