<?php

namespace App\Modules\Finance\Exports;

use App\Modules\Finance\Models\Payment;
use App\Modules\Pos\Models\Sale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Unified "money in" export for a company.
 *
 * Combines two sources that both represent recognised income:
 *   1. Finance payments of type "inbound" that have been posted.
 *   2. Completed POS sales (whose revenue is posted to the ledger via
 *      CompleteSale). POS never creates Payment rows, so there is no overlap
 *      and no double counting.
 */
class IncomeExport extends SpreadsheetExport
{
    public function __construct(
        private readonly int $companyId,
        private readonly ?string $from = null,
        private readonly ?string $to = null,
    ) {}

    public function collection(): Collection
    {
        // Stream lean rows (selected columns + joined partner name) so only
        // the final 6-field arrays are ever held, not hydrated model graphs.
        $payments = Payment::query()
            ->where('payments.company_id', $this->companyId)
            ->leftJoin('partners', 'partners.id', '=', 'payments.partner_id')
            ->where('payments.payment_type', 'inbound')
            ->where('payments.status', 'posted')
            ->when($this->from, fn ($q) => $q->where('payments.payment_date', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->where('payments.payment_date', '<=', $this->to))
            ->select([
                'payments.payment_date',
                'payments.payment_number',
                'payments.payment_method',
                'payments.amount',
                'partners.name as partner_name',
            ])
            ->cursor()
            ->map(fn (Payment $p): array => [
                'date' => optional($p->payment_date)->format('Y-m-d') ?? '',
                'source' => 'Payment',
                'reference' => (string) $p->payment_number,
                'party' => (string) ($p->partner_name ?? ''),
                'method' => (string) ($p->payment_method ?? ''),
                'amount' => (float) $p->amount,
            ]);

        $sales = Sale::query()
            ->where('sales.company_id', $this->companyId)
            ->leftJoin('partners', 'partners.id', '=', 'sales.partner_id')
            ->where('sales.status', 'completed')
            ->when($this->from, fn ($q) => $q->where('sales.completed_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->where('sales.completed_at', '<', Carbon::parse($this->to)->addDay()->toDateString()))
            ->select([
                'sales.completed_at',
                'sales.order_date',
                'sales.sale_number',
                'sales.customer_name',
                'sales.total',
                'partners.name as partner_name',
            ])
            ->cursor()
            ->map(fn (Sale $s): array => [
                'date' => optional($s->completed_at ?? $s->order_date)->format('Y-m-d') ?? '',
                'source' => 'POS Sale',
                'reference' => (string) $s->sale_number,
                'party' => (string) ($s->customer_name ?: ($s->partner_name ?? '')),
                'method' => 'POS',
                'amount' => (float) $s->total,
            ]);

        return $payments->collect()->concat($sales->collect())->sortBy('date')->values();
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Date', 'Source', 'Reference', 'Party', 'Method', 'Amount'];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, mixed>
     */
    public function map(array $row): array
    {
        return [
            $row['date'],
            $row['source'],
            $row['reference'],
            $row['party'],
            $row['method'],
            $row['amount'],
        ];
    }

    public function title(): string
    {
        return 'Income';
    }
}
