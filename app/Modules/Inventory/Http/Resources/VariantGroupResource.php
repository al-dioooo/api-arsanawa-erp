<?php

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VariantGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'unit_of_measure_id' => $this->unit_of_measure_id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'unit' => new UnitOfMeasureResource($this->whenLoaded('unit')),
            'variants' => VariantResource::collection($this->whenLoaded('variants')),
        ];
    }
}
