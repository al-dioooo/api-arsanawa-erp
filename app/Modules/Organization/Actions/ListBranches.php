<?php

namespace App\Modules\Organization\Actions;

use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Company;
use Illuminate\Database\Eloquent\Collection;

class ListBranches
{
    /**
     * @return Collection<int, Branch>
     */
    public function execute(Company $company): Collection
    {
        return $company->branches()
            ->where('status', 'active')
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
    }
}
