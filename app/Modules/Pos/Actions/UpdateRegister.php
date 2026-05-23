<?php

namespace App\Modules\Pos\Actions;

use App\Models\User;
use App\Modules\Pos\Models\Register;
use Illuminate\Support\Facades\DB;

class UpdateRegister
{
    /**
     * @param  array{name?: string, code?: string, branch_id?: int, cash_account_id?: int|null, is_active?: bool}  $data
     */
    public function execute(Register $register, User $user, array $data): Register
    {
        return DB::transaction(function () use ($register, $user, $data): Register {
            $register->fill($data);
            $register->updated_by = $user->id;
            $register->save();

            return $register->load(['branch', 'cashAccount']);
        });
    }
}
