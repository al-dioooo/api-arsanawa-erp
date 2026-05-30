<?php

namespace App\Modules\Pos\Actions;

use App\Modules\Pos\Models\CashierShift;
use App\Modules\Pos\Models\Register;
use App\Modules\Pos\Models\Sale;

class GetPosDashboardSummary
{
    /**
     * @return array{
     *     counters: array{
     *         registers: array{total: int, active: int},
     *         shifts: array{open: int},
     *         sales: array{open: int, today_count: int, today_total: string}
     *     }
     * }
     */
    public function execute(int $companyId): array
    {
        $todaySales = Sale::query()
            ->forCompany($companyId)
            ->whereDate('order_date', now()->toDateString())
            ->where('status', '!=', 'void');

        return [
            'counters' => [
                'registers' => [
                    'total' => Register::query()->forCompany($companyId)->count(),
                    'active' => Register::query()->forCompany($companyId)->where('is_active', true)->count(),
                ],
                'shifts' => [
                    'open' => CashierShift::query()->forCompany($companyId)->where('status', 'open')->count(),
                ],
                'sales' => [
                    'open' => Sale::query()->forCompany($companyId)->whereIn('status', ['draft', 'confirmed'])->count(),
                    'today_count' => (clone $todaySales)->count(),
                    'today_total' => number_format((float) (clone $todaySales)->sum('total'), 4, '.', ''),
                ],
            ],
        ];
    }
}
