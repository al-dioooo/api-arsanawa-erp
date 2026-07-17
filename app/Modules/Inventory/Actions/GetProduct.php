<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\Product;

class GetProduct
{
    public function execute(int $productId, int $companyId): Product
    {
        return Product::query()
            ->forCompany($companyId)
            ->with(['category', 'brand', 'baseUom', 'variants.availabilities', 'productUnits.images', 'productUnits.variants.group.unit', 'images', 'tags'])
            ->findOrFail($productId);
    }
}
