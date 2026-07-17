<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\Account;
use Illuminate\Support\Facades\DB;

class GetAccountLedger
{
    /**
     * Get the Account Ledger report.
     *
     * @return array{
     *     account: array{id: int, code: string, name: string, type: string, normal_balance: string},
     *     ledger: array<int, array{
     *         id: int,
     *         journal_entry_id: int,
     *         entry_number: string,
     *         entry_date: string,
     *         description: string,
     *         debit: float,
     *         credit: float,
     *         balance: float
     *     }>
     * }
     */
    public function execute(int $companyId, int $accountId, int $periodId): array
    {
        $account = Account::query()->forCompany($companyId)->findOrFail($accountId);

        // Running balance is computed in SQL so PHP never has to accumulate
        // over the whole period, and rows stream via cursor().
        $signedAmount = $account->normal_balance === 'debit'
            ? '(jl.debit - jl.credit)'
            : '(jl.credit - jl.debit)';

        $lines = DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->where('jl.account_id', $accountId)
            ->where('je.company_id', $companyId)
            ->where('je.accounting_period_id', $periodId)
            ->where('je.status', 'posted')
            ->select([
                'jl.id',
                'je.id as journal_entry_id',
                'je.entry_number',
                'je.entry_date',
                'je.description as entry_description',
                'jl.description as line_description',
                'jl.debit',
                'jl.credit',
            ])
            ->selectRaw("SUM({$signedAmount}) OVER (ORDER BY je.entry_date, je.id, jl.id) as running_balance")
            ->orderBy('je.entry_date')
            ->orderBy('je.id')
            ->orderBy('jl.id')
            ->cursor();

        $ledger = [];
        foreach ($lines as $line) {
            $ledger[] = [
                'id' => (int) $line->id,
                'journal_entry_id' => (int) $line->journal_entry_id,
                'entry_number' => $line->entry_number,
                'entry_date' => $line->entry_date,
                'description' => $line->line_description ?: $line->entry_description,
                'debit' => (float) $line->debit,
                'credit' => (float) $line->credit,
                'balance' => (float) $line->running_balance,
            ];
        }

        return [
            'account' => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'normal_balance' => $account->normal_balance,
            ],
            'ledger' => $ledger,
        ];
    }
}
