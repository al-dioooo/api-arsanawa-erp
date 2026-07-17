<?php

namespace App\Modules\Finance\Services;

use App\Models\User;
use App\Modules\Finance\Models\AccountingPeriod;
use App\Modules\Finance\Models\AccountMapping;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Models\JournalLine;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PostingService
{
    /**
     * The id of the open accounting period covering a date.
     *
     * Null when no period covers the date, or the one that does is not open —
     * callers decide how to word that failure for their own document.
     */
    public function findOpenPeriodId(int $companyId, DateTimeInterface|string $date): ?int
    {
        $period = AccountingPeriod::query()
            ->forCompany($companyId)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();

        return $period && $period->status === 'open' ? $period->id : null;
    }

    /**
     * The account a company maps a well-known key to, or null when unmapped.
     */
    public function findMappedAccountId(int $companyId, string $key): ?int
    {
        return AccountMapping::query()
            ->forCompany($companyId)
            ->where('key', $key)
            ->value('account_id');
    }

    /**
     * Record a journal entry for a system-generated document and post it.
     *
     * For callers that own their entry number and reference (a POS sale, say),
     * unlike the user-facing CreateJournalEntry action which allocates both.
     *
     * @param  array{
     *     company_id: int,
     *     branch_id?: int|null,
     *     entry_number: string,
     *     entry_date: mixed,
     *     accounting_period_id: int,
     *     description: string,
     *     reference_type?: string|null,
     *     reference_id?: int|null,
     *     currency_id: int|null,
     *     exchange_rate: mixed,
     *     lines: array<array{
     *         account_id: int,
     *         description?: string|null,
     *         debit: mixed,
     *         credit: mixed,
     *         foreign_debit?: mixed,
     *         foreign_credit?: mixed
     *     }>
     * }  $data
     * @return int The posted entry id.
     *
     * @throws ValidationException
     */
    public function recordPosted(array $data, User $user): int
    {
        return DB::transaction(function () use ($data, $user): int {
            $entry = JournalEntry::create([
                'company_id' => $data['company_id'],
                'branch_id' => $data['branch_id'] ?? null,
                'entry_number' => $data['entry_number'],
                'entry_date' => $data['entry_date'],
                'accounting_period_id' => $data['accounting_period_id'],
                'description' => $data['description'],
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'currency_id' => $data['currency_id'],
                'exchange_rate' => $data['exchange_rate'],
                'status' => 'draft',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            foreach ($data['lines'] as $line) {
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $line['account_id'],
                    'description' => $line['description'] ?? null,
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'foreign_debit' => $line['foreign_debit'] ?? $line['debit'],
                    'foreign_credit' => $line['foreign_credit'] ?? $line['credit'],
                ]);
            }

            $this->post($entry, $user);

            return $entry->id;
        });
    }

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
