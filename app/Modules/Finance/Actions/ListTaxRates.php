<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\TaxRate;
use Illuminate\Database\Eloquent\Collection;

class ListTaxRates
{
    public function execute(int $companyId): Collection
    {
        return TaxRate::query()
            ->forCompany($companyId)
            ->orderBy('name')
            ->get();
    }
}
