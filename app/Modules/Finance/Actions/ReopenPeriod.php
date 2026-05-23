<?php

namespace App\Modules\Finance\Actions;

use App\Models\User;
use App\Modules\Finance\Models\AccountingPeriod;
use Illuminate\Validation\ValidationException;

class ReopenPeriod
{
    public function execute(AccountingPeriod $period, User $user): AccountingPeriod
    {
        if ($period->status === 'open') {
            throw ValidationException::withMessages([
                'status' => [__('This period is already open.')],
            ]);
        }

        $period->status = 'open';
        $period->closed_at = null;
        $period->closed_by = null;
        $period->updated_by = $user->id;
        $period->save();

        return $period;
    }
}
