<?php

namespace App\Modules\Organization\Http\Requests\Concerns;

use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\Membership;

trait AuthorizesOrganizationRequests
{
    protected function routeCompany(): ?Company
    {
        $company = $this->route('company');

        if ($company instanceof Company) {
            return $company;
        }

        if (is_numeric($company)) {
            return Company::find((int) $company);
        }

        return null;
    }

    protected function activeMembershipForRouteCompany(): ?Membership
    {
        $company = $this->routeCompany();
        $user = $this->user();

        if (! $company || ! $user) {
            return null;
        }

        return Membership::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();
    }

    protected function isActiveCompanyMember(): bool
    {
        $membership = $this->activeMembershipForRouteCompany();

        if (! $membership) {
            return false;
        }

        setPermissionsTeamId($membership->company_id);

        return true;
    }

    protected function canForRouteCompany(string $permission): bool
    {
        if (! $this->isActiveCompanyMember()) {
            return false;
        }

        return $this->user()?->can($permission) ?? false;
    }
}
