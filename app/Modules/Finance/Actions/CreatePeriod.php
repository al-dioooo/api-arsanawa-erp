<?php

namespace App\Modules\Finance\Actions;

use App\Models\User;
use App\Modules\Finance\Models\AccountingPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatePeriod
{
    /**
     * @param  array{name: string, start_date: string, end_date: string}  $data
     */
    public function execute(int $companyId, User $user, array $data): AccountingPeriod
    {
        return DB::transaction(function () use ($companyId, $user, $data): AccountingPeriod {
            $overlaps = AccountingPeriod::query()
                ->forCompany($companyId)
                ->where('start_date', '<=', $data['end_date'])
                ->where('end_date', '>=', $data['start_date'])
                ->exists();

            if ($overlaps) {
                throw ValidationException::withMessages([
                    'start_date' => [__('The date range overlaps an existing period.')],
                ]);
            }

            return AccountingPeriod::create([
                'company_id' => $companyId,
                'name' => $data['name'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'status' => 'open',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        });
    }
}
