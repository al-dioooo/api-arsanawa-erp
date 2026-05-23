<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\AccountingPeriod;
use Illuminate\Database\Eloquent\Collection;

class ListPeriods
{
    public function execute(int $companyId): Collection
    {
        return AccountingPeriod::query()
            ->forCompany($companyId)
            ->orderBy('start_date')
            ->get();
    }
}
