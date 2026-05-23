<?php

namespace App\Modules\Finance\Actions;

use App\Models\User;
use App\Modules\Finance\Models\AccountingPeriod;
use App\Modules\Finance\Models\AccountMapping;
use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Models\JournalLine;
use App\Modules\Finance\Services\ApprovalService;
use App\Modules\Finance\Services\PostingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PostBill
{
    protected PostingService $postingService;

    public function __construct(PostingService $postingService)
    {
        $this->postingService = $postingService;
    }

    /**
     * Post a vendor bill to the General Ledger.
     *
     * @throws ValidationException
     */
    public function execute(Bill $bill, User $user): Bill
    {
        if ($bill->status !== 'draft') {
            throw ValidationException::withMessages([
                'bill' => [__('Only draft bills can be posted.')],
            ]);
        }

        if (! app(ApprovalService::class)->isApproved($bill)) {
            throw ValidationException::withMessages([
                'bill' => [__('The bill must be approved before it can be posted.')],
            ]);
        }

        // 1. Resolve open accounting period
        $period = AccountingPeriod::query()
            ->forCompany($bill->company_id)
            ->where('start_date', '<=', $bill->bill_date)
            ->where('end_date', '>=', $bill->bill_date)
            ->first();

        if (! $period) {
            throw ValidationException::withMessages([
                'bill_date' => [__('No accounting period found for the bill date.')],
            ]);
        }

        if ($period->status !== 'open') {
            throw ValidationException::withMessages([
                'bill_date' => [__('The accounting period for the bill date is closed.')],
            ]);
        }

        // 2. Resolve accounts payable mapping
        $apMapping = AccountMapping::query()
            ->forCompany($bill->company_id)
            ->where('key', 'accounts_payable')
            ->first();

        if (! $apMapping) {
            throw ValidationException::withMessages([
                'accounts_payable' => [__('Accounts payable mapping is missing for this company.')],
            ]);
        }
        $apAccountId = $apMapping->account_id;

        // 3. Resolve purchase expense mapping (for lines without explicit account)
        $expenseMapping = AccountMapping::query()
            ->forCompany($bill->company_id)
            ->where('key', 'purchase_expense')
            ->first();
        $fallbackExpenseAccountId = $expenseMapping?->account_id;

        // 4. Resolve vat input mapping (if tax_total > 0)
        $vatAccountId = null;
        if (bccomp((string) $bill->tax_total, '0.0000', 4) > 0) {
            $vatMapping = AccountMapping::query()
                ->forCompany($bill->company_id)
                ->where('key', 'vat_input')
                ->first();

            if (! $vatMapping) {
                throw ValidationException::withMessages([
                    'vat_input' => [__('VAT input mapping is missing for this company.')],
                ]);
            }
            $vatAccountId = $vatMapping->account_id;
        }

        // 5. Resolve withholding tax payable mapping (if withholding_total > 0)
        $whtAccountId = null;
        if (bccomp((string) $bill->withholding_total, '0.0000', 4) > 0) {
            $whtMapping = AccountMapping::query()
                ->forCompany($bill->company_id)
                ->where('key', 'withholding_tax_payable')
                ->first();

            if (! $whtMapping) {
                throw ValidationException::withMessages([
                    'withholding_tax_payable' => [__('Withholding tax payable mapping is missing for this company.')],
                ]);
            }
            $whtAccountId = $whtMapping->account_id;
        }

        return DB::transaction(function () use ($bill, $user, $period, $apAccountId, $fallbackExpenseAccountId, $vatAccountId, $whtAccountId): Bill {
            // Check each line has an expense account
            foreach ($bill->lines as $line) {
                $lineAccountId = $line->expense_account_id ?? $fallbackExpenseAccountId;
                if (! $lineAccountId) {
                    throw ValidationException::withMessages([
                        'expense_account' => [__('Expense account mapping is missing for bill line.')],
                    ]);
                }
            }

            // Create draft journal entry
            $entryNumber = 'JE-BILL-'.$bill->bill_number;

            $entry = JournalEntry::create([
                'company_id' => $bill->company_id,
                'branch_id' => $bill->branch_id,
                'entry_number' => $entryNumber,
                'entry_date' => $bill->bill_date,
                'accounting_period_id' => $period->id,
                'description' => 'System posted bill #'.$bill->bill_number,
                'reference_type' => 'Bill',
                'reference_id' => $bill->id,
                'currency_id' => $bill->currency_id,
                'exchange_rate' => $bill->exchange_rate,
                'status' => 'draft',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            // Debit Expense per line
            foreach ($bill->lines as $line) {
                $lineAccountId = $line->expense_account_id ?? $fallbackExpenseAccountId;

                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $lineAccountId,
                    'description' => 'Expense for line: '.$line->description,
                    'debit' => $line->line_subtotal,
                    'credit' => 0,
                    'foreign_debit' => $line->line_subtotal,
                    'foreign_credit' => 0,
                ]);
            }

            // Debit VAT Input (if applicable)
            if ($vatAccountId !== null) {
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $vatAccountId,
                    'description' => 'VAT Input for bill #'.$bill->bill_number,
                    'debit' => $bill->tax_total,
                    'credit' => 0,
                    'foreign_debit' => $bill->tax_total,
                    'foreign_credit' => 0,
                ]);
            }

            // Credit Accounts Payable
            JournalLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $apAccountId,
                'description' => 'Payable for bill #'.$bill->bill_number,
                'debit' => 0,
                'credit' => $bill->total,
                'foreign_debit' => 0,
                'foreign_credit' => $bill->total,
            ]);

            // Credit Withholding Tax Payable (if applicable)
            if ($whtAccountId !== null) {
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $whtAccountId,
                    'description' => 'Withholding Tax Payable for bill #'.$bill->bill_number,
                    'debit' => 0,
                    'credit' => $bill->withholding_total,
                    'foreign_debit' => 0,
                    'foreign_credit' => $bill->withholding_total,
                ]);
            }

            // Post to ledger
            $this->postingService->post($entry, $user);

            // Update bill status
            $bill->update([
                'status' => 'posted',
                'journal_entry_id' => $entry->id,
                'updated_by' => $user->id,
            ]);

            return $bill;
        });
    }
}
