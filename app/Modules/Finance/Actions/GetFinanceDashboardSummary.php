<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\ApprovalRequest;
use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Models\Invoice;
use Illuminate\Support\Carbon;

class GetFinanceDashboardSummary
{
    /**
     * @param  array{start_date?: string|null, end_date?: string|null}  $filters
     * @return array{
     *     counters: array{ar_outstanding: string, ap_outstanding: string, pending_approvals: int, draft_invoices: int, draft_bills: int},
     *     recent_activity: array<int, array{type: string, id: int, number: string, date: string|null, total: string, status: string}>,
     *     income_expense_series: array<int, array{date: string, income: float, expense: float}>
     * }
     */
    public function execute(int $companyId, array $filters = []): array
    {
        $arOutstanding = Invoice::query()
            ->forCompany($companyId)
            ->whereIn('status', ['posted', 'partially_paid', 'overdue'])
            ->selectRaw('SUM(total - amount_paid) as total')
            ->value('total') ?? 0;

        $apOutstanding = Bill::query()
            ->forCompany($companyId)
            ->whereIn('status', ['posted', 'partially_paid'])
            ->selectRaw('SUM(total - amount_paid) as total')
            ->value('total') ?? 0;

        return [
            'counters' => [
                'ar_outstanding' => number_format((float) $arOutstanding, 4, '.', ''),
                'ap_outstanding' => number_format((float) $apOutstanding, 4, '.', ''),
                'pending_approvals' => ApprovalRequest::query()
                    ->forCompany($companyId)
                    ->where('status', 'pending')
                    ->count(),
                'draft_invoices' => Invoice::query()->forCompany($companyId)->where('status', 'draft')->count(),
                'draft_bills' => Bill::query()->forCompany($companyId)->where('status', 'draft')->count(),
            ],
            'recent_activity' => $this->recentActivity($companyId),
            'income_expense_series' => $this->incomeExpenseSeries($companyId, $filters),
        ];
    }

    /**
     * @param  array{start_date?: string|null, end_date?: string|null}  $filters
     * @return array<int, array{date: string, income: float, expense: float}>
     */
    private function incomeExpenseSeries(int $companyId, array $filters): array
    {
        $startDate = filled($filters['start_date'] ?? null)
            ? Carbon::parse((string) $filters['start_date'])->startOfDay()
            : now()->startOfMonth()->startOfDay();
        $endDate = filled($filters['end_date'] ?? null)
            ? Carbon::parse((string) $filters['end_date'])->startOfDay()
            : now()->endOfMonth()->startOfDay();

        // Aggregate per day in SQL rather than hydrating every invoice/bill and
        // summing in PHP. Plain date comparisons keep the composite index usable.
        $incomeByDate = Invoice::query()
            ->forCompany($companyId)
            ->whereIn('status', ['posted', 'partially_paid', 'paid'])
            ->where('invoice_date', '>=', $startDate->toDateString())
            ->where('invoice_date', '<=', $endDate->toDateString())
            ->groupBy('invoice_date')
            ->selectRaw('invoice_date, SUM(total) as total')
            ->get()
            ->mapWithKeys(fn (Invoice $row): array => [$row->invoice_date->toDateString() => (float) $row->total]);

        $expenseByDate = Bill::query()
            ->forCompany($companyId)
            ->whereIn('status', ['posted', 'partially_paid', 'paid'])
            ->where('bill_date', '>=', $startDate->toDateString())
            ->where('bill_date', '<=', $endDate->toDateString())
            ->groupBy('bill_date')
            ->selectRaw('bill_date, SUM(total) as total')
            ->get()
            ->mapWithKeys(fn (Bill $row): array => [$row->bill_date->toDateString() => (float) $row->total]);

        $series = [];
        $cursor = $startDate->copy();

        while ($cursor->lte($endDate)) {
            $date = $cursor->toDateString();
            $series[] = [
                'date' => $date,
                'income' => $incomeByDate->get($date, 0.0),
                'expense' => $expenseByDate->get($date, 0.0),
            ];

            $cursor->addDay();
        }

        return $series;
    }

    /**
     * @return array<int, array{type: string, id: int, number: string, date: string|null, total: string, status: string}>
     */
    private function recentActivity(int $companyId): array
    {
        $invoices = Invoice::query()
            ->forCompany($companyId)
            ->latest('invoice_date')
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(fn (Invoice $invoice): array => [
                'type' => 'invoice',
                'id' => $invoice->id,
                'number' => $invoice->invoice_number,
                'date' => $invoice->invoice_date?->toDateString(),
                'total' => (string) $invoice->total,
                'status' => $invoice->status,
                'created_at' => $invoice->created_at?->timestamp ?? 0,
            ]);

        $bills = Bill::query()
            ->forCompany($companyId)
            ->latest('bill_date')
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(fn (Bill $bill): array => [
                'type' => 'bill',
                'id' => $bill->id,
                'number' => $bill->bill_number,
                'date' => $bill->bill_date?->toDateString(),
                'total' => (string) $bill->total,
                'status' => $bill->status,
                'created_at' => $bill->created_at?->timestamp ?? 0,
            ]);

        return $invoices
            ->concat($bills)
            ->sortByDesc(fn (array $item): string => sprintf(
                '%s-%010d-%d',
                $item['date'] ?? '',
                $item['created_at'],
                $item['type'] === 'bill' ? 1 : 0,
            ))
            ->take(8)
            ->map(fn (array $item): array => [
                'type' => $item['type'],
                'id' => $item['id'],
                'number' => $item['number'],
                'date' => $item['date'],
                'total' => $item['total'],
                'status' => $item['status'],
            ])
            ->values()
            ->all();
    }
}
