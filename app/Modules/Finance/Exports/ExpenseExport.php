<?php

namespace App\Modules\Finance\Exports;

use App\Modules\Finance\Models\Payment;
use Illuminate\Support\Collection;

/**
 * "Money out" export for a company: posted Finance payments of type "outbound"
 * (supplier/expense payments). All expense cash flows pass through Payments, so
 * this is the single source of truth for outgoing money.
 */
class ExpenseExport extends SpreadsheetExport
{
    public function __construct(
        private readonly int $companyId,
        private readonly ?string $from = null,
        private readonly ?string $to = null,
    ) {}

    public function collection(): Collection
    {
        return Payment::query()
            ->forCompany($this->companyId)
            ->where('payment_type', 'outbound')
            ->where('status', 'posted')
            ->when($this->from, fn ($q) => $q->whereDate('payment_date', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('payment_date', '<=', $this->to))
            ->with('partner')
            ->orderBy('payment_date')
            ->get()
            ->map(fn (Payment $p): array => [
                'date' => optional($p->payment_date)->format('Y-m-d') ?? '',
                'reference' => (string) $p->payment_number,
                'supplier' => (string) ($p->partner?->name ?? ''),
                'method' => (string) ($p->payment_method ?? ''),
                'amount' => (float) $p->amount,
            ]);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Date', 'Reference', 'Supplier', 'Method', 'Amount'];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, mixed>
     */
    public function map(array $row): array
    {
        return [
            $row['date'],
            $row['reference'],
            $row['supplier'],
            $row['method'],
            $row['amount'],
        ];
    }

    public function title(): string
    {
        return 'Expense';
    }
}
