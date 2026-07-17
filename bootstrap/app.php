<?php

use App\Http\Middleware\SecurityHeaders;
use App\Modules\Authentication\Providers\AuthenticationServiceProvider;
use App\Modules\Finance\Providers\FinanceServiceProvider;
use App\Modules\Identity\Providers\IdentityServiceProvider;
use App\Modules\Inventory\Providers\InventoryServiceProvider;
use App\Modules\Organization\Http\Middleware\AuthenticateExternalApiKey;
use App\Modules\Organization\Http\Middleware\SetCurrentCompany;
use App\Modules\Organization\Providers\OrganizationServiceProvider;
use App\Modules\Partners\Providers\PartnersServiceProvider;
use App\Modules\Platform\Providers\PlatformServiceProvider;
use App\Modules\Pos\Providers\PosServiceProvider;
use App\Providers\RateLimitServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        RateLimitServiceProvider::class,
        AuthenticationServiceProvider::class,
        IdentityServiceProvider::class,
        OrganizationServiceProvider::class,
        PlatformServiceProvider::class,
        PartnersServiceProvider::class,
        InventoryServiceProvider::class,
        FinanceServiceProvider::class,
        PosServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);

        // Every API route inherits a ceiling by default; new route groups are
        // therefore fail-closed. Limiters live in RateLimitServiceProvider.
        $middleware->throttleApi('api');

        // Secret settings treat null as "leave unchanged" so a client that reads
        // settings back (where credentials are redacted to null) and saves them
        // cannot wipe one. That makes an empty string the only way to explicitly
        // clear a credential, so it must survive as an empty string here.
        $middleware->convertEmptyStringsToNull(except: [
            fn (Request $request): bool => $request->is('api/v1/platform/settings'),
        ]);

        $middleware->alias([
            'external.api-key' => AuthenticateExternalApiKey::class,
            'organization.company-context' => SetCurrentCompany::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
