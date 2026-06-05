<?php

namespace App\Modules\Platform\Providers;

use App\Modules\Platform\Contracts\WhatsAppGateway;
use App\Modules\Platform\Services\WhatsApp\FonnteDriver;
use App\Modules\Platform\Services\WhatsApp\LogDriver;
use Illuminate\Support\ServiceProvider;

class PlatformServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(WhatsAppGateway::class, function (): WhatsAppGateway {
            $driver = (string) config('services.whatsapp.driver', 'log');

            return match ($driver) {
                'fonnte' => new FonnteDriver(
                    baseUrl: (string) config('services.whatsapp.fonnte.base_url'),
                    defaultToken: config('services.whatsapp.fonnte.token'),
                    defaultSender: config('services.whatsapp.fonnte.sender'),
                    timeoutSeconds: (int) config('services.whatsapp.fonnte.timeout', 15),
                ),
                default => new LogDriver(),
            };
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
