<?php

namespace App\Console\Commands;

use App\Support\PermissionCatalog;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class SyncPermissions extends Command
{
    protected $signature = 'permission:sync';

    protected $description = 'Sync the permission catalog into the permissions table.';

    public function handle(): int
    {
        $keys = PermissionCatalog::keys();

        foreach ($keys as $key) {
            Permission::findOrCreate($key, 'api');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info('Permissions synced: '.count($keys).'.');

        return self::SUCCESS;
    }
}
