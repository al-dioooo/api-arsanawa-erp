<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\VariantGroup;

class DeleteVariantGroup
{
    public function execute(VariantGroup $variantGroup): void
    {
        $variantGroup->delete();
    }
}
