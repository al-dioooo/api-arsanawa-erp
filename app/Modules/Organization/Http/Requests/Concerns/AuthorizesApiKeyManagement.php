<?php

namespace App\Modules\Organization\Http\Requests\Concerns;

use App\Modules\Organization\Models\Membership;
use App\Modules\Organization\Services\DeveloperAccess;

trait AuthorizesApiKeyManagement
{
    use AuthorizesOrganizationRequests;

    protected function canManageApiKeys(): bool
    {
        if (app(DeveloperAccess::class)->userIsDeveloper($this->user())) {
            return $this->routeCompany()?->status === 'active';
        }

        $membership = $this->activeMembershipForRouteCompany();

        if (! $membership instanceof Membership) {
            return false;
        }

        if ($membership->role === 'owner') {
            setPermissionsTeamId($membership->company_id);

            return true;
        }

        return $this->canForRouteCompany('organization.manage-api-keys');
    }
}
