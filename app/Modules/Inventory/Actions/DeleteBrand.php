<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\Brand;

class DeleteBrand
{
    public function execute(Brand $brand): void
    {
        $brand->delete();
    }
}
