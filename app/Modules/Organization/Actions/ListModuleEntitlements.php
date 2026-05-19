<?php

namespace App\Modules\Organization\Actions;

use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\ModuleEntitlement;
use Illuminate\Database\Eloquent\Collection;

class ListModuleEntitlements
{
    /**
     * @return Collection<int, ModuleEntitlement>
     */
    public function execute(Company $company): Collection
    {
        return $company->moduleEntitlements()
            ->orderBy('module')
            ->get();
    }
}
