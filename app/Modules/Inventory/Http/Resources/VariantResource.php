<?php

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'variant_group_id' => $this->variant_group_id,
            'name' => $this->name,
            'code' => $this->code,
            'position' => $this->position,
            'is_active' => $this->is_active,
            'group' => new VariantGroupResource($this->whenLoaded('group')),
        ];
    }
}
