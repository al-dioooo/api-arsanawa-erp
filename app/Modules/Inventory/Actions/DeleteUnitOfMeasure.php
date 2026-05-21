<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\UnitOfMeasure;

class DeleteUnitOfMeasure
{
    public function execute(UnitOfMeasure $unit): void
    {
        $unit->delete();
    }
}
