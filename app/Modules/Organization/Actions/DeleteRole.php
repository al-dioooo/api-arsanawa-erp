<?php

namespace App\Modules\Organization\Actions;

use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DeleteRole
{
    public function execute(Role $role): void
    {
        $role->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
