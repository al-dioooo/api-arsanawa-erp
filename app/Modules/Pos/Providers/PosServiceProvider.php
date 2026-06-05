<?php

namespace App\Modules\Pos\Providers;

use App\Modules\Pos\Events\SaleImported;
use App\Modules\Pos\Jobs\SendSaleReceipt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class PosServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Send a WhatsApp receipt for every newly imported sale. Dispatch is
        // deferred until the import transaction commits, so receipts are never
        // sent for rows that were rolled back.
        Event::listen(SaleImported::class, function (SaleImported $event): void {
            SendSaleReceipt::dispatch($event->saleId)->afterCommit();
        });
    }
}
