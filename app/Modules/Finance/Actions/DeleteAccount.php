<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\Account;
use Illuminate\Validation\ValidationException;

class DeleteAccount
{
    public function execute(Account $account): void
    {
        if ($account->children()->exists()) {
            throw ValidationException::withMessages([
                'account' => [__('Remove sub-accounts before deleting this account.')],
            ]);
        }

        $account->delete();
    }
}
