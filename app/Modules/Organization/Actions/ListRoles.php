<?php

namespace App\Modules\Organization\Actions;

use App\Modules\Organization\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\Permission\Models\Role;

class ListRoles
{
    public function execute(Company $company, int $perPage = 25): LengthAwarePaginator
    {
        return Role::query()
            ->where('team_id', $company->id)
            ->where('guard_name', 'api')
            ->with('permissions')
            ->orderBy('name')
            ->paginate($perPage);
    }
}
