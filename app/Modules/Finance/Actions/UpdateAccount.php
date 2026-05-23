<?php

namespace App\Modules\Finance\Actions;

use App\Models\User;
use App\Modules\Finance\Models\Account;

class UpdateAccount
{
    /**
     * @param  array{name?: string, is_active?: bool}  $data
     */
    public function execute(Account $account, User $user, array $data): Account
    {
        $account->fill(array_filter([
            'name' => $data['name'] ?? null,
            'is_active' => $data['is_active'] ?? null,
        ], fn ($v) => $v !== null));

        $account->updated_by = $user->id;
        $account->save();

        return $account;
    }
}
