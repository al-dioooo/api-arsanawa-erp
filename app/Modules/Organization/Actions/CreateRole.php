<?php

namespace App\Modules\Organization\Actions;

use App\Modules\Organization\Models\Company;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CreateRole
{
    /**
     * @param  array{name: string, permissions?: array<int, string>}  $data
     */
    public function execute(Company $company, array $data): Role
    {
        $previousTeamId = getPermissionsTeamId();
        setPermissionsTeamId($company->id);

        try {
            return DB::transaction(function () use ($data): Role {
                $role = Role::create(['name' => $data['name'], 'guard_name' => 'api']);

                $permissions = $data['permissions'] ?? [];

                foreach ($permissions as $permission) {
                    Permission::findOrCreate($permission, 'api');
                }

                $role->syncPermissions($permissions);

                return $role->load('permissions');
            });
        } finally {
            setPermissionsTeamId($previousTeamId);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
}
