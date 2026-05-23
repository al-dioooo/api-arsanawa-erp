<?php

namespace App\Modules\Pos\Actions;

use App\Modules\Pos\Models\Sale;
use App\Modules\Pos\Models\SalePayment;

class GetSalesReport
{
    /**
     * @param  array{from: string, to: string, branch_id?: int}  $filters
     * @return array<string, mixed>
     */
    public function execute(int $companyId, array $filters): array
    {
        $sales = Sale::query()
            ->forCompany($companyId)
            ->where('status', 'completed')
            ->whereBetween('order_date', [$filters['from'], $filters['to']])
            ->when(isset($filters['branch_id']), fn ($query) => $query->where('branch_id', $filters['branch_id']))
            ->get();

        $byType = [];
        foreach ($sales->groupBy('type') as $type => $group) {
            $byType[$type] = number_format((float) $group->sum('total'), 4, '.', '');
        }

        $saleIds = $sales->pluck('id');
        $payments = SalePayment::query()
            ->whereIn('sale_id', $saleIds)
            ->get()
            ->groupBy('method');

        $byPaymentMethod = [];
        foreach ($payments as $method => $group) {
            $byPaymentMethod[$method] = number_format((float) $group->sum('amount'), 4, '.', '');
        }

        return [
            'from' => $filters['from'],
            'to' => $filters['to'],
            'total_sales' => number_format((float) $sales->sum('total'), 4, '.', ''),
            'sale_count' => $sales->count(),
            'by_type' => $byType,
            'by_payment_method' => $byPaymentMethod,
        ];
    }
}
