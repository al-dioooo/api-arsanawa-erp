<?php

namespace App\Modules\Finance\Actions;

use App\Models\User;
use App\Modules\Finance\Models\Account;
use Illuminate\Support\Facades\DB;

class CreateAccount
{
    /**
     * @param  array{code: string, name: string, type: string, parent_id?: int|null, currency_id?: int|null, is_active?: bool}  $data
     */
    public function execute(int $companyId, User $user, array $data): Account
    {
        return DB::transaction(function () use ($companyId, $user, $data): Account {
            $parent = isset($data['parent_id'])
                ? Account::query()->forCompany($companyId)->findOrFail($data['parent_id'])
                : null;

            $normalBalance = match ($data['type']) {
                'asset', 'expense' => 'debit',
                'liability', 'equity', 'revenue' => 'credit',
            };

            $account = Account::create([
                'company_id' => $companyId,
                'parent_id' => $parent?->id,
                'code' => $data['code'],
                'name' => $data['name'],
                'type' => $data['type'],
                'normal_balance' => $normalBalance,
                'depth' => $parent !== null ? $parent->depth + 1 : 0,
                'is_postable' => true,
                'currency_id' => $data['currency_id'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            // When a child is added, the parent is no longer postable
            if ($parent !== null && $parent->is_postable) {
                $parent->is_postable = false;
                $parent->save();
            }

            return $account;
        });
    }
}
