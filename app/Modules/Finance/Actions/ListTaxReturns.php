<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\TaxReturn;
use Illuminate\Database\Eloquent\Collection;

class ListTaxReturns
{
    /**
     * List all tax returns for a company.
     */
    public function execute(int $companyId): Collection
    {
        return TaxReturn::query()
            ->forCompany($companyId)
            ->orderByDesc('period_start')
            ->orderByDesc('id')
            ->get();
    }
}
