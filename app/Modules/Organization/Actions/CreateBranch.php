<?php

namespace App\Modules\Organization\Actions;

use App\Models\User;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Company;
use Illuminate\Support\Facades\DB;

class CreateBranch
{
    /**
     * @param  array{name: string, code?: string|null, is_primary?: bool}  $data
     */
    public function execute(Company $company, User $user, array $data): Branch
    {
        return DB::transaction(function () use ($company, $user, $data): Branch {
            if (($data['is_primary'] ?? false) === true) {
                $company->branches()->update(['is_primary' => false]);
            }

            return Branch::create([
                'company_id' => $company->id,
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'is_primary' => $data['is_primary'] ?? false,
                'status' => 'active',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        });
    }
}
