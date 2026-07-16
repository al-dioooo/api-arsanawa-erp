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
        // Rebuild the scoped sales query per aggregate (query builders are
        // stateful) and aggregate in SQL instead of hydrating the whole range.
        $scopedSales = fn () => Sale::query()
            ->forCompany($companyId)
            ->where('status', 'completed')
            ->whereBetween('order_date', [$filters['from'], $filters['to']])
            ->when(isset($filters['branch_id']), fn ($query) => $query->where('branch_id', $filters['branch_id']));

        $byType = [];
        foreach ($scopedSales()->groupBy('type')->selectRaw('type, SUM(total) as total')->get() as $row) {
            $byType[$row->type] = number_format((float) $row->total, 4, '.', '');
        }

        $totals = $scopedSales()
            ->selectRaw('COUNT(*) as sale_count, COALESCE(SUM(total), 0) as total_sales')
            ->first();

        $byPaymentMethod = [];
        $paymentRows = SalePayment::query()
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->where('sales.company_id', $companyId)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.order_date', [$filters['from'], $filters['to']])
            ->when(isset($filters['branch_id']), fn ($query) => $query->where('sales.branch_id', $filters['branch_id']))
            ->groupBy('sale_payments.method')
            ->selectRaw('sale_payments.method as method, SUM(sale_payments.amount) as amount')
            ->get();

        foreach ($paymentRows as $row) {
            $byPaymentMethod[$row->method] = number_format((float) $row->amount, 4, '.', '');
        }

        return [
            'from' => $filters['from'],
            'to' => $filters['to'],
            'total_sales' => number_format((float) $totals->total_sales, 4, '.', ''),
            'sale_count' => (int) $totals->sale_count,
            'by_type' => $byType,
            'by_payment_method' => $byPaymentMethod,
        ];
    }
}
