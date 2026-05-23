<?php

namespace App\Modules\Finance\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GetTrialBalance
{
    /**
     * Get the Trial Balance report.
     *
     * @return Collection<int, array{
     *     account_id: int,
     *     code: string,
     *     name: string,
     *     type: string,
     *     normal_balance: string,
     *     debit: float,
     *     credit: float
     * }>
     */
    public function execute(int $companyId, int $periodId): Collection
    {
        $results = DB::table('chart_of_accounts as coa')
            ->leftJoin('journal_lines as jl', 'jl.account_id', '=', 'coa.id')
            ->leftJoin('journal_entries as je', function ($join) use ($periodId) {
                $join->on('je.id', '=', 'jl.journal_entry_id')
                    ->where('je.status', '=', 'posted')
                    ->where('je.accounting_period_id', '=', $periodId);
            })
            ->where('coa.company_id', $companyId)
            ->select([
                'coa.id as account_id',
                'coa.code',
                'coa.name',
                'coa.type',
                'coa.normal_balance',
                DB::raw('COALESCE(SUM(CASE WHEN je.id IS NOT NULL THEN jl.debit ELSE 0 END), 0) as debit'),
                DB::raw('COALESCE(SUM(CASE WHEN je.id IS NOT NULL THEN jl.credit ELSE 0 END), 0) as credit'),
            ])
            ->groupBy('coa.id', 'coa.code', 'coa.name', 'coa.type', 'coa.normal_balance')
            ->orderBy('coa.code')
            ->get();

        return $results->map(function ($row) {
            return [
                'account_id' => (int) $row->account_id,
                'code' => $row->code,
                'name' => $row->name,
                'type' => $row->type,
                'normal_balance' => $row->normal_balance,
                'debit' => (float) $row->debit,
                'credit' => (float) $row->credit,
            ];
        });
    }
}
