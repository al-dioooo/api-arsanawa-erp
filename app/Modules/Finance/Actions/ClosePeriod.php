<?php

namespace App\Modules\Finance\Actions;

use App\Models\User;
use App\Modules\Finance\Models\AccountingPeriod;
use Illuminate\Validation\ValidationException;

class ClosePeriod
{
    public function execute(AccountingPeriod $period, User $user): AccountingPeriod
    {
        if ($period->status === 'closed') {
            throw ValidationException::withMessages([
                'status' => [__('This period is already closed.')],
            ]);
        }

        $period->status = 'closed';
        $period->closed_at = now();
        $period->closed_by = $user->id;
        $period->updated_by = $user->id;
        $period->save();

        return $period;
    }
}
