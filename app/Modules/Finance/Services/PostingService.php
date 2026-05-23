<?php

namespace App\Modules\Finance\Services;

use App\Models\User;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Models\JournalLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PostingService
{
    /**
     * Post a journal entry.
     *
     * @throws ValidationException
     */
    public function post(JournalEntry $entry, User $user): JournalEntry
    {
        return DB::transaction(function () use ($entry, $user): JournalEntry {
            // Check status
            if ($entry->status !== 'draft') {
                throw ValidationException::withMessages([
                    'journal_entry' => [__('Only draft journal entries can be posted.')],
                ]);
            }

            // Load relations
            $entry->load(['lines.account', 'period']);

            // Validate period is open
            $period = $entry->period;
            if (! $period || $period->status !== 'open') {
                throw ValidationException::withMessages([
                    'journal_entry' => [__('The accounting period is closed or invalid.')],
                ]);
            }

            // Check if there are lines
            if ($entry->lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'journal_entry' => [__('The journal entry has no lines.')],
                ]);
            }

            // Validate balance
            $debitSum = '0.0000';
            $creditSum = '0.0000';
            foreach ($entry->lines as $line) {
                // Validate account
                $account = $line->account;
                if (! $account) {
                    throw ValidationException::withMessages([
                        'account' => [__('One or more journal lines reference an invalid account.')],
                    ]);
                }
                if ($account->company_id !== $entry->company_id) {
                    throw ValidationException::withMessages([
                        'account' => [__('One or more accounts do not belong to this company.')],
                    ]);
                }
                if (! $account->is_postable) {
                    throw ValidationException::withMessages([
                        'account' => [__('Cannot post to a non-postable (parent) account.')],
                    ]);
                }
                if (! $account->is_active) {
                    throw ValidationException::withMessages([
                        'account' => [__('Cannot post to an inactive account.')],
                    ]);
                }

                $debitSum = bcadd($debitSum, (string) $line->debit, 4);
                $creditSum = bcadd($creditSum, (string) $line->credit, 4);
            }

            if (bccomp($debitSum, $creditSum, 4) !== 0) {
                throw ValidationException::withMessages([
                    'journal_entry' => [__('The journal entry is unbalanced. Total debits must equal total credits.')],
                ]);
            }

            // Update header status
            $entry->update([
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            return $entry;
        });
    }

    /**
     * Void a posted journal entry by creating a reversing entry.
     *
     * @throws ValidationException
     */
    public function reverse(JournalEntry $entry, User $user): JournalEntry
    {
        return DB::transaction(function () use ($entry, $user): JournalEntry {
            // Check status
            if ($entry->status !== 'posted') {
                throw ValidationException::withMessages([
                    'journal_entry' => [__('Only posted journal entries can be voided.')],
                ]);
            }

            // Load relations
            $entry->load(['lines.account', 'period']);

            // Validate period is open
            $period = $entry->period;
            if (! $period || $period->status !== 'open') {
                throw ValidationException::withMessages([
                    'journal_entry' => [__('Cannot void a journal entry in a closed accounting period.')],
                ]);
            }

            // Mark original entry as void
            $entry->update([
                'status' => 'void',
                'updated_by' => $user->id,
            ]);

            // Create reversing entry number
            $reversingNumber = 'REV-'.$entry->entry_number;

            // Create reversing header
            $reversingEntry = JournalEntry::create([
                'company_id' => $entry->company_id,
                'branch_id' => $entry->branch_id,
                'entry_number' => $reversingNumber,
                'entry_date' => $entry->entry_date,
                'accounting_period_id' => $entry->accounting_period_id,
                'description' => 'Reversal of entry #'.$entry->id.': '.$entry->description,
                'reference_type' => $entry->reference_type,
                'reference_id' => $entry->reference_id,
                'currency_id' => $entry->currency_id,
                'exchange_rate' => $entry->exchange_rate,
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by' => $user->id,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            // Copy and reverse lines
            foreach ($entry->lines as $line) {
                JournalLine::create([
                    'journal_entry_id' => $reversingEntry->id,
                    'account_id' => $line->account_id,
                    'description' => $line->description,
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                    'foreign_debit' => $line->foreign_credit,
                    'foreign_credit' => $line->foreign_debit,
                ]);
            }

            return $entry;
        });
    }
}
