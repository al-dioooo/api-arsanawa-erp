<?php

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Inventory\Models\Product;

class UpdateProduct
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Product $product, User $user, array $data): Product
    {
        $fields = ['category_id', 'brand_id', 'base_uom_id', 'name', 'description', 'track_stock', 'attributes', 'status'];

        $product->fill(array_intersect_key($data, array_flip($fields)));
        $product->updated_by = $user->id;
        $product->save();

        return $product->load(['category', 'brand', 'baseUom', 'variants', 'productUnits.images', 'images', 'tags']);
    }
}
