<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\Product;

class DeleteProduct
{
    public function execute(Product $product): void
    {
        $product->delete();
    }
}
