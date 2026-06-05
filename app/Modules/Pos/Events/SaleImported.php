<?php

namespace App\Modules\Pos\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired once for each NEW sale created by a spreadsheet/CSV import (not for
 * updates of existing imported orders). Carries only the sale id so listeners
 * load fresh state when they run.
 */
class SaleImported
{
    use Dispatchable;

    public function __construct(public readonly int $saleId) {}
}
