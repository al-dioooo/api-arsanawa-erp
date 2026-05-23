<?php

namespace App\Modules\Finance\Actions;

use App\Models\User;
use App\Modules\Finance\Models\AccountingPeriod;
use App\Modules\Finance\Models\AccountMapping;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Models\JournalLine;
use App\Modules\Finance\Services\PostingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PostInvoice
{
    protected PostingService $postingService;

    public function __construct(PostingService $postingService)
    {
        $this->postingService = $postingService;
    }

    /**
     * Post a customer invoice to the General Ledger.
     *
     * @throws ValidationException
     */
    public function execute(Invoice $invoice, User $user): Invoice
    {
        if ($invoice->status !== 'draft') {
            throw ValidationException::withMessages([
                'invoice' => [__('Only draft invoices can be posted.')],
            ]);
        }

        // 1. Resolve open accounting period
        $period = AccountingPeriod::query()
            ->forCompany($invoice->company_id)
            ->where('start_date', '<=', $invoice->invoice_date)
            ->where('end_date', '>=', $invoice->invoice_date)
            ->first();

        if (! $period) {
            throw ValidationException::withMessages([
                'invoice_date' => [__('No accounting period found for the invoice date.')],
            ]);
        }

        if ($period->status !== 'open') {
            throw ValidationException::withMessages([
                'invoice_date' => [__('The accounting period for the invoice date is closed.')],
            ]);
        }

        // 2. Resolve accounts receivable mapping
        $arMapping = AccountMapping::query()
            ->forCompany($invoice->company_id)
            ->where('key', 'accounts_receivable')
            ->first();

        if (! $arMapping) {
            throw ValidationException::withMessages([
                'accounts_receivable' => [__('Accounts receivable mapping is missing for this company.')],
            ]);
        }
        $arAccountId = $arMapping->account_id;

        // 3. Resolve sales revenue mapping (for lines without explicit account)
        $revenueMapping = AccountMapping::query()
            ->forCompany($invoice->company_id)
            ->where('key', 'sales_revenue')
            ->first();
        $fallbackRevenueAccountId = $revenueMapping?->account_id;

        // 4. Resolve vat output mapping (if tax_total > 0)
        $vatAccountId = null;
        if (bccomp((string) $invoice->tax_total, '0.0000', 4) > 0) {
            $vatMapping = AccountMapping::query()
                ->forCompany($invoice->company_id)
                ->where('key', 'vat_output')
                ->first();

            if (! $vatMapping) {
                throw ValidationException::withMessages([
                    'vat_output' => [__('VAT output mapping is missing for this company.')],
                ]);
            }
            $vatAccountId = $vatMapping->account_id;
        }

        return DB::transaction(function () use ($invoice, $user, $period, $arAccountId, $fallbackRevenueAccountId, $vatAccountId): Invoice {
            // Check each line has a revenue account
            foreach ($invoice->lines as $line) {
                $lineAccountId = $line->revenue_account_id ?? $fallbackRevenueAccountId;
                if (! $lineAccountId) {
                    throw ValidationException::withMessages([
                        'revenue_account' => [__('Revenue account mapping is missing for invoice line.')],
                    ]);
                }
            }

            // Create draft journal entry
            $entryNumber = 'JE-INV-'.$invoice->invoice_number;

            $entry = JournalEntry::create([
                'company_id' => $invoice->company_id,
                'branch_id' => $invoice->branch_id,
                'entry_number' => $entryNumber,
                'entry_date' => $invoice->invoice_date,
                'accounting_period_id' => $period->id,
                'description' => 'System posted invoice #'.$invoice->invoice_number,
                'reference_type' => 'Invoice',
                'reference_id' => $invoice->id,
                'currency_id' => $invoice->currency_id,
                'exchange_rate' => $invoice->exchange_rate,
                'status' => 'draft',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            // Debit Accounts Receivable
            JournalLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $arAccountId,
                'description' => 'Receivable for invoice #'.$invoice->invoice_number,
                'debit' => $invoice->total,
                'credit' => 0,
                'foreign_debit' => $invoice->total,
                'foreign_credit' => 0,
            ]);

            // Credit Revenue per line
            foreach ($invoice->lines as $line) {
                $lineAccountId = $line->revenue_account_id ?? $fallbackRevenueAccountId;

                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $lineAccountId,
                    'description' => 'Revenue for line: '.$line->description,
                    'debit' => 0,
                    'credit' => $line->line_subtotal,
                    'foreign_debit' => 0,
                    'foreign_credit' => $line->line_subtotal,
                ]);
            }

            // Credit VAT Output
            if ($vatAccountId !== null) {
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $vatAccountId,
                    'description' => 'VAT Output for invoice #'.$invoice->invoice_number,
                    'debit' => 0,
                    'credit' => $invoice->tax_total,
                    'foreign_debit' => 0,
                    'foreign_credit' => $invoice->tax_total,
                ]);
            }

            // Post to ledger
            $this->postingService->post($entry, $user);

            // Update invoice status
            $invoice->update([
                'status' => 'posted',
                'journal_entry_id' => $entry->id,
                'updated_by' => $user->id,
            ]);

            return $invoice;
        });
    }
}
