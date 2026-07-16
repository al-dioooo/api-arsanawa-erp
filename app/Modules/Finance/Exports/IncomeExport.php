<?php

namespace App\Modules\Finance\Exports;

use App\Modules\Finance\Models\Payment;
use App\Modules\Pos\Models\Sale;
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
        $payments = Payment::query()
            ->forCompany($this->companyId)
            ->where('payment_type', 'inbound')
            ->where('status', 'posted')
            ->when($this->from, fn ($q) => $q->whereDate('payment_date', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('payment_date', '<=', $this->to))
            ->with('partner')
            ->get()
            ->map(fn (Payment $p): array => [
                'date' => optional($p->payment_date)->format('Y-m-d') ?? '',
                'source' => 'Payment',
                'reference' => (string) $p->payment_number,
                'party' => (string) ($p->partner?->name ?? ''),
                'method' => (string) ($p->payment_method ?? ''),
                'amount' => (float) $p->amount,
            ]);

        $sales = Sale::query()
            ->forCompany($this->companyId)
            ->where('status', 'completed')
            ->when($this->from, fn ($q) => $q->whereDate('completed_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('completed_at', '<=', $this->to))
            ->with('partner')
            ->get()
            ->map(fn (Sale $s): array => [
                'date' => optional($s->completed_at ?? $s->order_date)->format('Y-m-d') ?? '',
                'source' => 'POS Sale',
                'reference' => (string) $s->sale_number,
                'party' => (string) ($s->customer_name ?: ($s->partner?->name ?? '')),
                'method' => 'POS',
                'amount' => (float) $s->total,
            ]);

        return $payments->concat($sales)->sortBy('date')->values();
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
