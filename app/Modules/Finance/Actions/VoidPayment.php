<?php

namespace App\Modules\Finance\Actions;

use App\Models\User;
use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Services\PostingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoidPayment
{
    protected PostingService $postingService;

    public function __construct(PostingService $postingService)
    {
        $this->postingService = $postingService;
    }

    /**
     * Void (reverse) a posted payment.
     *
     * @throws ValidationException
     */
    public function execute(Payment $payment, User $user): Payment
    {
        if ($payment->status !== 'posted') {
            throw ValidationException::withMessages([
                'payment' => [__('Only posted payments can be voided.')],
            ]);
        }

        $payment->load(['journalEntry', 'allocations']);

        if (! $payment->journalEntry) {
            throw ValidationException::withMessages([
                'payment' => [__('No journal entry found for this payment.')],
            ]);
        }

        return DB::transaction(function () use ($payment, $user): Payment {
            // Reverse the GL entries
            $this->postingService->reverse($payment->journalEntry, $user);

            // Revert amount_paid on targeted documents
            foreach ($payment->allocations as $alloc) {
                if ($payment->payment_type === 'inbound') {
                    $invoice = Invoice::query()->where('id', $alloc->invoice_id)->lockForUpdate()->firstOrFail();
                    $newAmountPaid = bcsub((string) $invoice->amount_paid, (string) $alloc->amount, 4);
                    $newStatus = bccomp($newAmountPaid, '0.0000', 4) === 0 ? 'posted' : 'partially_paid';
                    $invoice->update([
                        'amount_paid' => $newAmountPaid,
                        'status' => $newStatus,
                        'updated_by' => $user->id,
                    ]);
                } else {
                    $bill = Bill::query()->where('id', $alloc->bill_id)->lockForUpdate()->firstOrFail();
                    $newAmountPaid = bcsub((string) $bill->amount_paid, (string) $alloc->amount, 4);
                    $newStatus = bccomp($newAmountPaid, '0.0000', 4) === 0 ? 'posted' : 'partially_paid';
                    $bill->update([
                        'amount_paid' => $newAmountPaid,
                        'status' => $newStatus,
                        'updated_by' => $user->id,
                    ]);
                }
            }

            // Update status
            $payment->update([
                'status' => 'void',
                'updated_by' => $user->id,
            ]);

            return $payment;
        });
    }
}
