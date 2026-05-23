<?php

namespace App\Modules\Finance\Actions;

use App\Models\User;
use App\Modules\Finance\Models\AccountingPeriod;
use App\Modules\Finance\Models\AccountMapping;
use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Models\JournalLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Services\ApprovalService;
use App\Modules\Finance\Services\PostingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PostPayment
{
    protected PostingService $postingService;

    public function __construct(PostingService $postingService)
    {
        $this->postingService = $postingService;
    }

    /**
     * Post a payment to the General Ledger.
     *
     * @throws ValidationException
     */
    public function execute(Payment $payment, User $user): Payment
    {
        if ($payment->status !== 'draft') {
            throw ValidationException::withMessages([
                'payment' => [__('Only draft payments can be posted.')],
            ]);
        }

        if (! app(ApprovalService::class)->isApproved($payment)) {
            throw ValidationException::withMessages([
                'payment' => [__('The payment must be approved before it can be posted.')],
            ]);
        }

        // 1. Resolve open accounting period
        $period = AccountingPeriod::query()
            ->forCompany($payment->company_id)
            ->where('start_date', '<=', $payment->payment_date)
            ->where('end_date', '>=', $payment->payment_date)
            ->first();

        if (! $period) {
            throw ValidationException::withMessages([
                'payment_date' => [__('No accounting period found for the payment date.')],
            ]);
        }

        if ($period->status !== 'open') {
            throw ValidationException::withMessages([
                'payment_date' => [__('The accounting period for the payment date is closed.')],
            ]);
        }

        // 2. Pre-calculate allocation and unallocated totals
        $payment->load('allocations');
        $totalAllocated = '0.0000';
        foreach ($payment->allocations as $alloc) {
            $totalAllocated = bcadd($totalAllocated, (string) $alloc->amount, 4);
        }

        $unallocated = bcsub((string) $payment->amount, $totalAllocated, 4);
        if (bccomp($unallocated, '0.0000', 4) < 0) {
            throw ValidationException::withMessages([
                'amount' => [__('The total allocation amount cannot exceed the payment amount.')],
            ]);
        }

        // 3. Resolve account mapping requirements
        $arAccountId = null;
        $apAccountId = null;
        $prepayAccountId = null;

        if ($payment->payment_type === 'inbound') {
            if (bccomp($totalAllocated, '0.0000', 4) > 0) {
                $arMapping = AccountMapping::query()
                    ->forCompany($payment->company_id)
                    ->where('key', 'accounts_receivable')
                    ->first();

                if (! $arMapping) {
                    throw ValidationException::withMessages([
                        'accounts_receivable' => [__('Accounts receivable mapping is missing for this company.')],
                    ]);
                }
                $arAccountId = $arMapping->account_id;
            }

            if (bccomp($unallocated, '0.0000', 4) > 0) {
                $prepayMapping = AccountMapping::query()
                    ->forCompany($payment->company_id)
                    ->where('key', 'customer_prepayments')
                    ->first();

                if (! $prepayMapping) {
                    throw ValidationException::withMessages([
                        'customer_prepayments' => [__('Customer prepayments mapping is missing for this company.')],
                    ]);
                }
                $prepayAccountId = $prepayMapping->account_id;
            }
        } else { // outbound
            if (bccomp($totalAllocated, '0.0000', 4) > 0) {
                $apMapping = AccountMapping::query()
                    ->forCompany($payment->company_id)
                    ->where('key', 'accounts_payable')
                    ->first();

                if (! $apMapping) {
                    throw ValidationException::withMessages([
                        'accounts_payable' => [__('Accounts payable mapping is missing for this company.')],
                    ]);
                }
                $apAccountId = $apMapping->account_id;
            }

            if (bccomp($unallocated, '0.0000', 4) > 0) {
                $prepayMapping = AccountMapping::query()
                    ->forCompany($payment->company_id)
                    ->where('key', 'vendor_prepayments')
                    ->first();

                if (! $prepayMapping) {
                    throw ValidationException::withMessages([
                        'vendor_prepayments' => [__('Vendor prepayments mapping is missing for this company.')],
                    ]);
                }
                $prepayAccountId = $prepayMapping->account_id;
            }
        }

        return DB::transaction(function () use ($payment, $user, $period, $unallocated, $arAccountId, $apAccountId, $prepayAccountId): Payment {
            // Re-validate allocations against current database state (lock target docs)
            foreach ($payment->allocations as $alloc) {
                if ($payment->payment_type === 'inbound') {
                    $invoice = Invoice::query()->where('id', $alloc->invoice_id)->lockForUpdate()->firstOrFail();
                    if (! in_array($invoice->status, ['posted', 'partially_paid'])) {
                        throw ValidationException::withMessages([
                            'allocations' => [__('One or more targeted invoices are not in a postable state.')],
                        ]);
                    }
                    $remaining = bcsub((string) $invoice->total, (string) $invoice->amount_paid, 4);
                    if (bccomp((string) $alloc->amount, $remaining, 4) > 0) {
                        throw ValidationException::withMessages([
                            'allocations' => [__('Allocation amount exceeds the remaining unpaid balance of the invoice.')],
                        ]);
                    }
                } else {
                    $bill = Bill::query()->where('id', $alloc->bill_id)->lockForUpdate()->firstOrFail();
                    if (! in_array($bill->status, ['posted', 'partially_paid'])) {
                        throw ValidationException::withMessages([
                            'allocations' => [__('One or more targeted bills are not in a postable state.')],
                        ]);
                    }
                    $remaining = bcsub((string) $bill->total, (string) $bill->amount_paid, 4);
                    if (bccomp((string) $alloc->amount, $remaining, 4) > 0) {
                        throw ValidationException::withMessages([
                            'allocations' => [__('Allocation amount exceeds the remaining unpaid balance of the bill.')],
                        ]);
                    }
                }
            }

            // Create draft journal entry
            $entryNumber = 'JE-PAY-'.$payment->payment_number;

            $entry = JournalEntry::create([
                'company_id' => $payment->company_id,
                'branch_id' => $payment->branch_id,
                'entry_number' => $entryNumber,
                'entry_date' => $payment->payment_date,
                'accounting_period_id' => $period->id,
                'description' => 'System posted payment #'.$payment->payment_number,
                'reference_type' => 'Payment',
                'reference_id' => $payment->id,
                'currency_id' => $payment->currency_id,
                'exchange_rate' => $payment->exchange_rate,
                'status' => 'draft',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            if ($payment->payment_type === 'inbound') {
                // Debit Cash/Bank
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $payment->cash_account_id,
                    'description' => 'Debit Cash/Bank for payment #'.$payment->payment_number,
                    'debit' => $payment->amount,
                    'credit' => 0,
                    'foreign_debit' => $payment->amount,
                    'foreign_credit' => 0,
                ]);

                // Credit Accounts Receivable for each allocation
                foreach ($payment->allocations as $alloc) {
                    JournalLine::create([
                        'journal_entry_id' => $entry->id,
                        'account_id' => $arAccountId,
                        'description' => 'Credit Accounts Receivable for invoice allocation #'.$alloc->invoice_id,
                        'debit' => 0,
                        'credit' => $alloc->amount,
                        'foreign_debit' => 0,
                        'foreign_credit' => $alloc->amount,
                    ]);

                    // Propagate amount_paid and status to invoice
                    $invoice = Invoice::findOrFail($alloc->invoice_id);
                    $newAmountPaid = bcadd((string) $invoice->amount_paid, (string) $alloc->amount, 4);
                    $newStatus = bccomp($newAmountPaid, (string) $invoice->total, 4) === 0 ? 'paid' : 'partially_paid';
                    $invoice->update([
                        'amount_paid' => $newAmountPaid,
                        'status' => $newStatus,
                        'updated_by' => $user->id,
                    ]);
                }

                // Credit Customer Prepayment if unallocated > 0
                if (bccomp($unallocated, '0.0000', 4) > 0) {
                    JournalLine::create([
                        'journal_entry_id' => $entry->id,
                        'account_id' => $prepayAccountId,
                        'description' => 'Credit Customer Prepayments for unallocated amount of payment #'.$payment->payment_number,
                        'debit' => 0,
                        'credit' => $unallocated,
                        'foreign_debit' => 0,
                        'foreign_credit' => $unallocated,
                    ]);
                }
            } else { // outbound
                // Credit Cash/Bank
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $payment->cash_account_id,
                    'description' => 'Credit Cash/Bank for payment #'.$payment->payment_number,
                    'debit' => 0,
                    'credit' => $payment->amount,
                    'foreign_debit' => 0,
                    'foreign_credit' => $payment->amount,
                ]);

                // Debit Accounts Payable for each allocation
                foreach ($payment->allocations as $alloc) {
                    JournalLine::create([
                        'journal_entry_id' => $entry->id,
                        'account_id' => $apAccountId,
                        'description' => 'Debit Accounts Payable for bill allocation #'.$alloc->bill_id,
                        'debit' => $alloc->amount,
                        'credit' => 0,
                        'foreign_debit' => $alloc->amount,
                        'foreign_credit' => 0,
                    ]);

                    // Propagate amount_paid and status to bill
                    $bill = Bill::findOrFail($alloc->bill_id);
                    $newAmountPaid = bcadd((string) $bill->amount_paid, (string) $alloc->amount, 4);
                    $newStatus = bccomp($newAmountPaid, (string) $bill->total, 4) === 0 ? 'paid' : 'partially_paid';
                    $bill->update([
                        'amount_paid' => $newAmountPaid,
                        'status' => $newStatus,
                        'updated_by' => $user->id,
                    ]);
                }

                // Debit Vendor Prepayment if unallocated > 0
                if (bccomp($unallocated, '0.0000', 4) > 0) {
                    JournalLine::create([
                        'journal_entry_id' => $entry->id,
                        'account_id' => $prepayAccountId,
                        'description' => 'Debit Vendor Prepayments for unallocated amount of payment #'.$payment->payment_number,
                        'debit' => $unallocated,
                        'credit' => 0,
                        'foreign_debit' => $unallocated,
                        'foreign_credit' => 0,
                    ]);
                }
            }

            // Post to ledger
            $this->postingService->post($entry, $user);

            // Update payment status
            $payment->update([
                'status' => 'posted',
                'journal_entry_id' => $entry->id,
                'updated_by' => $user->id,
            ]);

            return $payment;
        });
    }
}
