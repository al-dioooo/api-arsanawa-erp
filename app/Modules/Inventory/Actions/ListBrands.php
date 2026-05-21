<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\Brand;
use Illuminate\Database\Eloquent\Collection;

class ListBrands
{
    /**
     * @return Collection<int, Brand>
     */
    public function execute(int $companyId): Collection
    {
        return Brand::query()->forCompany($companyId)->orderBy('name')->get();
    }
}
