<?php

namespace App\Modules\Partners\Http\Requests\Concerns;

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

        return $this->user()?->can($permission) ?? false;
    }
}
