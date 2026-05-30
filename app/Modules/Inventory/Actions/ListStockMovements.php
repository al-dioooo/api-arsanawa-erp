<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Pagination\LengthAwarePaginator;

class ListStockMovements
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(int $companyId, array $params): LengthAwarePaginator
    {
        $query = StockMovement::query()
            ->where('company_id', $companyId)
            ->with(['variant', 'productUnit.product', 'productUnit.variants.group']);

        if (isset($params['branch_id'])) {
            $query->where('branch_id', (int) $params['branch_id']);
        }

        if (isset($params['product_variant_id'])) {
            $query->where('product_variant_id', (int) $params['product_variant_id']);
        }

        if (isset($params['product_unit_id'])) {
            $query->where('product_unit_id', (int) $params['product_unit_id']);
        }

        return $query->orderByDesc('occurred_at')
            ->paginate($params['per_page'] ?? 15);
    }
}
