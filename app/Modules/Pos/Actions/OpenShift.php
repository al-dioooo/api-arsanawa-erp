<?php

namespace App\Modules\Pos\Actions;

use App\Models\User;
use App\Modules\Pos\Models\CashierShift;
use App\Modules\Pos\Models\Register;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpenShift
{
    /**
     * @param  array{register_id: int, opening_float: numeric|string|float, notes?: string|null}  $data
     *
     * @throws ValidationException
     */
    public function execute(int $companyId, User $user, array $data): CashierShift
    {
        return DB::transaction(function () use ($companyId, $user, $data): CashierShift {
            $register = Register::query()
                ->forCompany($companyId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->findOrFail($data['register_id']);

            $hasOpenShift = CashierShift::query()
                ->where('register_id', $register->id)
                ->where('status', 'open')
                ->exists();

            if ($hasOpenShift) {
                throw ValidationException::withMessages([
                    'register_id' => [__('This register already has an open shift.')],
                ]);
            }

            return CashierShift::create([
                'company_id' => $companyId,
                'branch_id' => $register->branch_id,
                'register_id' => $register->id,
                'user_id' => $user->id,
                'status' => 'open',
                'opened_at' => now(),
                'opening_float' => $data['opening_float'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ])->load(['register', 'user']);
        });
    }
}
