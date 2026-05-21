<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Collection;

class ListUnitsOfMeasure
{
    /**
     * @return Collection<int, UnitOfMeasure>
     */
    public function execute(int $companyId): Collection
    {
        return UnitOfMeasure::query()->forCompany($companyId)->orderBy('code')->get();
    }
}
