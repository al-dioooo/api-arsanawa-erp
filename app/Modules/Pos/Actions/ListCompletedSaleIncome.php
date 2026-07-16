<?php

namespace App\Modules\Pos\Actions;

use App\Modules\Pos\Models\Sale;
use App\Modules\Pos\Support\SaleIncomeRow;
use Illuminate\Support\Carbon;
use Illuminate\Support\LazyCollection;

class ListCompletedSaleIncome
{
    /**
     * Read contract for other modules: completed sales as income rows.
     *
     * Streams lean rows (selected columns + joined partner name) so only the
     * final DTOs are ever held, not hydrated model graphs.
     *
     * @param  string|null  $from  Inclusive lower bound on the completion date.
     * @param  string|null  $to  Inclusive upper bound on the completion date.
     * @return LazyCollection<int, SaleIncomeRow>
     */
    public function execute(int $companyId, ?string $from = null, ?string $to = null): LazyCollection
    {
        return Sale::query()
            ->where('sales.company_id', $companyId)
            ->leftJoin('partners', 'partners.id', '=', 'sales.partner_id')
            ->where('sales.status', 'completed')
            ->when($from, fn ($q) => $q->where('sales.completed_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('sales.completed_at', '<', Carbon::parse($to)->addDay()->toDateString()))
            ->select([
                'sales.completed_at',
                'sales.order_date',
                'sales.sale_number',
                'sales.customer_name',
                'sales.total',
                'partners.name as partner_name',
            ])
            ->cursor()
            ->map(fn (Sale $sale): SaleIncomeRow => new SaleIncomeRow(
                date: optional($sale->completed_at ?? $sale->order_date)->format('Y-m-d') ?? '',
                reference: (string) $sale->sale_number,
                party: (string) ($sale->customer_name ?: ($sale->partner_name ?? '')),
                amount: (float) $sale->total,
            ));
    }
}
