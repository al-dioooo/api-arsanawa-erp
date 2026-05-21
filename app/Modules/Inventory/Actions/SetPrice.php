<?php

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Inventory\Models\Price;
use App\Modules\Inventory\Models\PriceList;

class SetPrice
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(PriceList $priceList, User $user, array $data): Price
    {
        return Price::create([
            'price_list_id' => $priceList->id,
            'product_variant_id' => $data['product_variant_id'],
            'price' => $data['price'],
            'maximum_retail_price' => $data['maximum_retail_price'] ?? null,
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }
}
