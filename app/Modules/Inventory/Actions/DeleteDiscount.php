<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\Discount;

class DeleteDiscount
{
    public function execute(Discount $discount): void
    {
        $discount->delete();
    }
}
