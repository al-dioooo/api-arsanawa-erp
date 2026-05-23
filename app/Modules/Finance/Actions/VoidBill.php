<?php

namespace App\Modules\Finance\Actions;

use App\Models\User;
use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Services\PostingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoidBill
{
    protected PostingService $postingService;

    public function __construct(PostingService $postingService)
    {
        $this->postingService = $postingService;
    }

    /**
     * Void a posted vendor bill and reverse ledger entries.
     *
     * @throws ValidationException
     */
    public function execute(Bill $bill, User $user): Bill
    {
        if ($bill->status !== 'posted') {
            throw ValidationException::withMessages([
                'bill' => [__('Only posted bills can be voided.')],
            ]);
        }

        $entry = $bill->journalEntry;
        if (! $entry) {
            throw ValidationException::withMessages([
                'bill' => [__('The bill is missing a linked journal entry.')],
            ]);
        }

        return DB::transaction(function () use ($bill, $entry, $user): Bill {
            // Reverse the journal entry
            $this->postingService->reverse($entry, $user);

            // Update bill status
            $bill->update([
                'status' => 'void',
                'updated_by' => $user->id,
            ]);

            return $bill;
        });
    }
}
