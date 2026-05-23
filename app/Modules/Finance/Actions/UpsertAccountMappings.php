<?php

namespace App\Modules\Finance\Actions;

use App\Models\User;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\AccountMapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpsertAccountMappings
{
    /**
     * @param  array<int, array{key: string, account_id: int}>  $mappings
     */
    public function execute(int $companyId, User $user, array $mappings): array
    {
        return DB::transaction(function () use ($companyId, $user, $mappings): array {
            $result = [];

            foreach ($mappings as $mapping) {
                $account = Account::query()
                    ->forCompany($companyId)
                    ->find($mapping['account_id']);

                if (! $account) {
                    throw ValidationException::withMessages([
                        'mappings' => [__('Account :id does not belong to this company.', ['id' => $mapping['account_id']])],
                    ]);
                }

                $result[] = AccountMapping::updateOrCreate(
                    [
                        'company_id' => $companyId,
                        'key' => $mapping['key'],
                    ],
                    [
                        'account_id' => $mapping['account_id'],
                        'updated_by' => $user->id,
                        'created_by' => $user->id,
                    ],
                );
            }

            return $result;
        });
    }
}
