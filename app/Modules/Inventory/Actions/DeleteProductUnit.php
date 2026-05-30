<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\ProductUnit;

class DeleteProductUnit
{
    public function execute(ProductUnit $productUnit): void
    {
        $productUnit->delete();
    }
}
