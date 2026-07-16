<?php

namespace App\Modules\Inventory\Http\Resources\External;

use App\Modules\Inventory\Models\Price;
use App\Modules\Inventory\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Variant entry exposed on the external product catalog, combined with the
 * price resolved for the requested date (or null when no price applies).
 *
 * The shape is consumed by external clients and must not change.
 */
class ExternalProductVariantResource extends JsonResource
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
            'id' => $this->id,
            'product_id' => $this->product_id,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'attributes' => $this->attributes,
            'price' => $this->price?->price,
            'maximum_retail_price' => $this->price?->maximum_retail_price,
            'currency_id' => $this->price?->priceList?->currency_id,
            'product_unit' => $this->productUnit($request),
        ];
    }

    private function productUnit(Request $request): ?array
    {
        $unit = $this->price?->productUnit;

        if ($unit === null) {
            return null;
        }

        return [
            'id' => $unit->id,
            'sku' => $unit->sku,
            'barcode' => $unit->barcode,
            'name' => $unit->name,
            'images' => ExternalProductImageResource::collection($unit->images)->resolve($request),
        ];
    }
}
