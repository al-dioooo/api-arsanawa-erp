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
        $stockValue = StockLot::query()
            ->forCompany($companyId)
            ->active()
            ->selectRaw('SUM(remaining_quantity * unit_cost) as total')
            ->value('total') ?? 0;

        return [
            'counters' => [
                'products' => [
                    'total' => Product::query()->forCompany($companyId)->count(),
                    'active' => Product::query()->forCompany($companyId)->where('status', 'active')->count(),
                    'inactive' => Product::query()->forCompany($companyId)->where('status', 'inactive')->count(),
                ],
                'product_units' => [
                    'total' => ProductUnit::query()->forCompany($companyId)->count(),
                    'active' => ProductUnit::query()->forCompany($companyId)->where('is_active', true)->count(),
                ],
                'stock_lots' => [
                    'active' => StockLot::query()->forCompany($companyId)->active()->count(),
                    'expiring_soon' => StockLot::query()
                        ->forCompany($companyId)
                        ->active()
                        ->whereNotNull('expiry_date')
                        ->whereBetween('expiry_date', [now()->toDateString(), now()->addDays(30)->toDateString()])
                        ->count(),
                ],
                'stock_movements' => [
                    'total' => StockMovement::query()->forCompany($companyId)->count(),
                    'unsettled' => StockTransfer::query()
                        ->where('company_id', $companyId)
                        ->where('status', '!=', 'completed')
                        ->count(),
                ],
                'stock_value' => number_format((float) $stockValue, 4, '.', ''),
            ],
        ];
    }
}
