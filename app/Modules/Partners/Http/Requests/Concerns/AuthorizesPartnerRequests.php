<?php

namespace App\Modules\Partners\Http\Requests\Concerns;

use App\Modules\Organization\Services\DeveloperAccess;

trait AuthorizesPartnerRequests
{
    protected function activeCompanyId(): ?int
    {
        $id = $this->attributes->get('active_company_id');

        return $id !== null ? (int) $id : null;
    }

    protected function canInActiveCompany(string $permission): bool
    {
        $companyId = $this->activeCompanyId();

        if ($companyId === null) {
            return false;
        }

        setPermissionsTeamId($companyId);

        if (app(DeveloperAccess::class)->userIsDeveloper($this->user())) {
            return true;
        }

        return $this->user()?->can($permission) ?? false;
    }
}
