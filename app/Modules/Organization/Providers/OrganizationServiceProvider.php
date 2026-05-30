<?php

namespace App\Modules\Organization\Providers;

use App\Modules\Organization\Services\BranchPermission;
use App\Modules\Organization\Services\DeveloperAccess;
use Illuminate\Support\ServiceProvider;

class OrganizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BranchPermission::class);
        $this->app->singleton(DeveloperAccess::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
