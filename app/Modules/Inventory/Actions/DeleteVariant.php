<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\Variant;

class DeleteVariant
{
    public function execute(Variant $variant): void
    {
        $variant->delete();
    }
}
