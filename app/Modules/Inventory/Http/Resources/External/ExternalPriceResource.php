<?php

namespace App\Modules\Inventory\Http\Resources\External;

use App\Modules\Inventory\Models\Price;
use App\Modules\Inventory\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Flat price payload for the external price lookup endpoint, combining the
 * variant with the price resolved for the requested date (all price fields
 * are null when no price applies).
 *
 * The shape is consumed by external clients and must not change.
 */
class ExternalPriceResource extends JsonResource
{
    public function __construct(
        ProductVariant $variant,
        private readonly ?Price $price,
    ) {
        parent::__construct($variant);
    }

    public function toArray(Request $request): array
    {
        return [
            'product_variant_id' => $this->id,
            'price' => $this->price?->price,
            'maximum_retail_price' => $this->price?->maximum_retail_price,
            'currency_id' => $this->price?->priceList?->currency_id,
        ];
    }
}
