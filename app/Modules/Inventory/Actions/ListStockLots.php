<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\StockLot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListStockLots
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(int $companyId, array $params): LengthAwarePaginator
    {
        $query = StockLot::query()
            ->where('company_id', $companyId)
            ->where('status', 'active');

        if (isset($params['branch_id'])) {
            $query->where('branch_id', (int) $params['branch_id']);
        }

        if (isset($params['product_variant_id'])) {
            $query->where('product_variant_id', (int) $params['product_variant_id']);
        }

        if (isset($params['product_unit_id'])) {
            $query->where('product_unit_id', (int) $params['product_unit_id']);
        }

        if (isset($params['expiring_before'])) {
            $query->whereNotNull('expiry_date')
                ->where('expiry_date', '<=', $params['expiring_before']);
        }

        return $query->with(['productUnit.product', 'productUnit.variants.group'])
            ->orderBy('received_at')
            ->paginate((int) ($params['per_page'] ?? 50));
    }
}
