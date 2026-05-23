<?php

namespace App\Modules\Finance\Actions;

use App\Models\User;
use App\Modules\Finance\Models\AccountingPeriod;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Models\JournalLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateJournalEntry
{
    /**
     * Create a manual journal entry in draft status.
     *
     * @param  array{
     *     entry_date: string,
     *     description: string,
     *     currency_id: int,
     *     exchange_rate: float,
     *     branch_id?: int|null,
     *     lines: array<array{
     *         account_id: int,
     *         description?: string|null,
     *         debit: float,
     *         credit: float,
     *         foreign_debit?: float|null,
     *         foreign_credit?: float|null
     *     }>
     * }  $data
     *
     * @throws ValidationException
     */
    public function execute(int $companyId, User $user, array $data): JournalEntry
    {
        // 1. Resolve accounting period
        $period = AccountingPeriod::query()
            ->forCompany($companyId)
            ->where('start_date', '<=', $data['entry_date'])
            ->where('end_date', '>=', $data['entry_date'])
            ->first();

        if (! $period) {
            throw ValidationException::withMessages([
                'entry_date' => [__('No accounting period found for the entry date.')],
            ]);
        }

        // 2. Generate entry number
        $entryNumber = 'JE-'.now()->format('YmdHis').'-'.str_pad((string) rand(0, 9999), 4, '0', STR_PAD_LEFT);

        return DB::transaction(function () use ($companyId, $user, $data, $period, $entryNumber): JournalEntry {
            $entry = JournalEntry::create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'] ?? null,
                'entry_number' => $entryNumber,
                'entry_date' => $data['entry_date'],
                'accounting_period_id' => $period->id,
                'description' => $data['description'],
                'currency_id' => $data['currency_id'],
                'exchange_rate' => $data['exchange_rate'],
                'status' => 'draft',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            foreach ($data['lines'] as $lineData) {
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $lineData['account_id'],
                    'description' => $lineData['description'] ?? null,
                    'debit' => $lineData['debit'],
                    'credit' => $lineData['credit'],
                    'foreign_debit' => $lineData['foreign_debit'] ?? $lineData['debit'],
                    'foreign_credit' => $lineData['foreign_credit'] ?? $lineData['credit'],
                ]);
            }

            return $entry->load('lines');
        });
    }
}
