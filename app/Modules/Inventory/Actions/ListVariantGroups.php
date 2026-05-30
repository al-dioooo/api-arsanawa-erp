<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\VariantGroup;
use Illuminate\Database\Eloquent\Collection;

class ListVariantGroups
{
    /**
     * @return Collection<int, VariantGroup>
     */
    public function execute(int $companyId): Collection
    {
        return VariantGroup::query()
            ->forCompany($companyId)
            ->with(['unit', 'variants' => fn ($query) => $query->orderBy('position')->orderBy('name')])
            ->orderBy('name')
            ->get();
    }
}
