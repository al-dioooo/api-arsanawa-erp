<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\Category;
use Illuminate\Database\Eloquent\Collection;

class ListCategories
{
    /**
     * @return Collection<int, Category>
     */
    public function execute(int $companyId): Collection
    {
        return Category::query()
            ->forCompany($companyId)
            ->orderBy('path')
            ->get();
    }
}
