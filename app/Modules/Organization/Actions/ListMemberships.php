<?php

namespace App\Modules\Organization\Actions;

use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\Membership;
use Illuminate\Database\Eloquent\Collection;

class ListMemberships
{
    /**
     * @return Collection<int, Membership>
     */
    public function execute(Company $company): Collection
    {
        return $company->memberships()
            ->with(['user', 'branch'])
            ->orderBy('role')
            ->orderBy('id')
            ->get();
    }
}
