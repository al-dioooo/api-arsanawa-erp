<?php

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductUnitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'product_id' => $this->product_id,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'product' => new ProductResource($this->whenLoaded('product')),
            'variants' => VariantResource::collection($this->whenLoaded('variants')),
        ];
    }
}
