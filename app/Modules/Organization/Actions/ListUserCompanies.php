<?php

namespace App\Modules\Organization\Actions;

use App\Models\User;
use App\Modules\Organization\Models\Membership;
use Illuminate\Database\Eloquent\Collection;

class ListUserCompanies
{
    /**
     * Return active memberships with their companies for the authenticated user.
     *
     * @return Collection<int, Membership>
     */
    public function execute(User $user): Collection
    {
        return $user->memberships()
            ->with(['company', 'branch'])
            ->where('memberships.status', 'active')
            ->whereHas('company', fn ($query) => $query->where('companies.status', 'active'))
            ->join('companies', 'memberships.company_id', '=', 'companies.id')
            ->orderBy('companies.name')
            ->select('memberships.*')
            ->get();
    }
}
