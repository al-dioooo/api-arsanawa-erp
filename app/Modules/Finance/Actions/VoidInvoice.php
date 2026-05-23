<?php

namespace App\Modules\Finance\Actions;

use App\Models\User;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Services\PostingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoidInvoice
{
    protected PostingService $postingService;

    public function __construct(PostingService $postingService)
    {
        $this->postingService = $postingService;
    }

    /**
     * Void a posted customer invoice and reverse ledger entries.
     *
     * @throws ValidationException
     */
    public function execute(Invoice $invoice, User $user): Invoice
    {
        if ($invoice->status !== 'posted') {
            throw ValidationException::withMessages([
                'invoice' => [__('Only posted invoices can be voided.')],
            ]);
        }

        $entry = $invoice->journalEntry;
        if (! $entry) {
            throw ValidationException::withMessages([
                'invoice' => [__('The invoice is missing a linked journal entry.')],
            ]);
        }

        return DB::transaction(function () use ($invoice, $entry, $user): Invoice {
            // Reverse the journal entry
            $this->postingService->reverse($entry, $user);

            // Update invoice status
            $invoice->update([
                'status' => 'void',
                'updated_by' => $user->id,
            ]);

            return $invoice;
        });
    }
}
