<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\AccountMapping;
use Illuminate\Database\Eloquent\Collection;

class ListAccountMappings
{
    public function execute(int $companyId): Collection
    {
        return AccountMapping::query()
            ->forCompany($companyId)
            ->with('account')
            ->get();
    }
}
