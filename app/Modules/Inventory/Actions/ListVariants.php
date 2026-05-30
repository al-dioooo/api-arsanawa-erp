<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\Variant;
use Illuminate\Database\Eloquent\Collection;

class ListVariants
{
    /**
     * @param  array{variant_group_id?: int}  $filters
     * @return Collection<int, Variant>
     */
    public function execute(int $companyId, array $filters = []): Collection
    {
        $query = Variant::query()
            ->forCompany($companyId)
            ->with('group.unit');

        if (isset($filters['variant_group_id'])) {
            $query->where('variant_group_id', $filters['variant_group_id']);
        }

        return $query->orderBy('variant_group_id')->orderBy('position')->orderBy('name')->get();
    }
}
