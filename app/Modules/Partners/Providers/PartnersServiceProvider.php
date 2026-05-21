<?php

namespace App\Modules\Partners\Providers;

use Illuminate\Support\ServiceProvider;

class PartnersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
