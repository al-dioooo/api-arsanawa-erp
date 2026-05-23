<?php

namespace App\Modules\Pos\Actions;

use App\Models\User;
use App\Modules\Pos\Models\Register;
use Illuminate\Support\Facades\DB;

class CreateRegister
{
    /**
     * @param  array{name: string, code: string, branch_id: int, cash_account_id?: int|null, is_active?: bool}  $data
     */
    public function execute(int $companyId, User $user, array $data): Register
    {
        return DB::transaction(function () use ($companyId, $user, $data): Register {
            return Register::create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'],
                'name' => $data['name'],
                'code' => $data['code'],
                'cash_account_id' => $data['cash_account_id'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ])->load(['branch', 'cashAccount']);
        });
    }
}
