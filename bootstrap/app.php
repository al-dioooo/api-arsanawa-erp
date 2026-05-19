<?php

use App\Modules\Authentication\Providers\AuthenticationServiceProvider;
use App\Modules\Identity\Providers\IdentityServiceProvider;
use App\Modules\Organization\Http\Middleware\SetCurrentCompany;
use App\Modules\Organization\Providers\OrganizationServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        AuthenticationServiceProvider::class,
        IdentityServiceProvider::class,
        OrganizationServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'organization.company-context' => SetCurrentCompany::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
