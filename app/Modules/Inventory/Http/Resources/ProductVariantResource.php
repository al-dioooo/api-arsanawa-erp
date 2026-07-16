<?php

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'company_id' => $this->company_id,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'attributes' => $this->attributes,
            'purchase_uom_id' => $this->purchase_uom_id,
            'purchase_conversion_factor' => $this->purchase_conversion_factor,
            'is_active' => $this->is_active,
            // A variant with no row for a branch is available there.
            'branch_availability' => $this->whenLoaded('availabilities', fn () => $this->availabilities->map(fn ($availability): array => [
                'branch_id' => $availability->branch_id,
                'is_available' => $availability->is_available,
                'is_exclusive' => $availability->is_exclusive,
            ])->values()->all()),
        ];
    }
}
