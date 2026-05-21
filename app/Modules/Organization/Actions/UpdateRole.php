<?php

namespace App\Modules\Organization\Actions;

use App\Modules\Organization\Models\Company;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UpdateRole
{
    /**
     * @param  array{name?: string, permissions?: array<int, string>}  $data
     */
    public function execute(Company $company, Role $role, array $data): Role
    {
        $previousTeamId = getPermissionsTeamId();
        setPermissionsTeamId($company->id);

        try {
            return DB::transaction(function () use ($role, $data): Role {
                if (array_key_exists('name', $data)) {
                    $role->name = $data['name'];
                    $role->save();
                }

                if (array_key_exists('permissions', $data)) {
                    foreach ($data['permissions'] as $permission) {
                        Permission::findOrCreate($permission, 'api');
                    }

                    $role->syncPermissions($data['permissions']);
                }

                return $role->load('permissions');
            });
        } finally {
            setPermissionsTeamId($previousTeamId);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
}
