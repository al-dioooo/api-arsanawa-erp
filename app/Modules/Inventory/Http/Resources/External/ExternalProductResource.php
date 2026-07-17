<?php

namespace App\Modules\Inventory\Http\Resources\External;

use App\Modules\Inventory\Models\Price;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Product entry exposed on the external product catalog.
 *
 * Prices and branch availability are batch-resolved by the controller and
 * passed in as maps keyed by variant id, so serializing a page of products
 * never issues a query per variant. Variants hidden for the requested branch
 * are omitted; a variant without an availability row is available.
 *
 * The shape is consumed by external clients and must not change.
 */
class ExternalProductResource extends JsonResource
{
    /**
     * @param  array<int, Price>  $priceMap
     * @param  array<int, bool>  $availabilityMap
     */
    public function __construct(
        Product $product,
        private readonly array $priceMap,
        private readonly array $availabilityMap,
    ) {
        parent::__construct($product);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'attributes' => $this->attributes,
            'images' => ExternalProductImageResource::collection($this->images)->resolve($request),
            'variants' => $this->variants
                ->filter(fn (ProductVariant $variant): bool => $this->availabilityMap[$variant->id] ?? true)
                ->map(fn (ProductVariant $variant): array => (new ExternalProductVariantResource(
                    $variant,
                    $this->priceMap[$variant->id] ?? null,
                ))->resolve($request))
                ->values()
                ->all(),
        ];
    }
}
