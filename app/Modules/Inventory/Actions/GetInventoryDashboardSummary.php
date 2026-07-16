<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\ProductUnit;
use App\Modules\Inventory\Models\StockLot;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockTransfer;

class GetInventoryDashboardSummary
{
    /**
     * @return array{
     *     counters: array{
     *         products: array{total: int, active: int, inactive: int},
     *         product_units: array{total: int, active: int},
     *         stock_lots: array{active: int, expiring_soon: int},
     *         stock_movements: array{total: int, unsettled: int},
     *         stock_value: string
     *     }
     * }
     */
    public function execute(int $companyId): array
    {
        // One conditionally-aggregated query per table instead of a count per
        // counter.
        $products = Product::query()
            ->forCompany($companyId)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END), 0) as active")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END), 0) as inactive")
            ->first();

        $productUnits = ProductUnit::query()
            ->forCompany($companyId)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COALESCE(SUM(CASE WHEN is_active THEN 1 ELSE 0 END), 0) as active')
            ->first();

        $lots = StockLot::query()
            ->forCompany($companyId)
            ->active()
            ->selectRaw('COUNT(*) as active_count')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN expiry_date BETWEEN ? AND ? THEN 1 ELSE 0 END), 0) as expiring_soon',
                [now()->toDateString(), now()->addDays(30)->toDateString()],
            )
            ->selectRaw('COALESCE(SUM(remaining_quantity * unit_cost), 0) as stock_value')
            ->first();

        return [
            'counters' => [
                'products' => [
                    'total' => (int) $products->total,
                    'active' => (int) $products->active,
                    'inactive' => (int) $products->inactive,
                ],
                'product_units' => [
                    'total' => (int) $productUnits->total,
                    'active' => (int) $productUnits->active,
                ],
                'stock_lots' => [
                    'active' => (int) $lots->active_count,
                    'expiring_soon' => (int) $lots->expiring_soon,
                ],
                'stock_movements' => [
                    'total' => StockMovement::query()->forCompany($companyId)->count(),
                    'unsettled' => StockTransfer::query()
                        ->where('company_id', $companyId)
                        ->where('status', '!=', 'completed')
                        ->count(),
                ],
                'stock_value' => number_format((float) $lots->stock_value, 4, '.', ''),
            ],
        ];
    }
}
