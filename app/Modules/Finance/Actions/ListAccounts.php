<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\Account;
use Illuminate\Database\Eloquent\Collection;

class ListAccounts
{
    public function execute(int $companyId): Collection
    {
        return Account::query()
            ->forCompany($companyId)
            ->orderBy('code')
            ->get();
    }
}
