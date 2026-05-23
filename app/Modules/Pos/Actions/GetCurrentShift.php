<?php

namespace App\Modules\Pos\Actions;

use App\Modules\Pos\Models\CashierShift;

class GetCurrentShift
{
    public function execute(int $companyId, ?int $registerId = null): ?CashierShift
    {
        return CashierShift::query()
            ->forCompany($companyId)
            ->with(['register', 'user'])
            ->where('status', 'open')
            ->when($registerId !== null, fn ($query) => $query->where('register_id', $registerId))
            ->latest('opened_at')
            ->first();
    }
}
