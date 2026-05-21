<?php

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'path' => $this->path,
            'depth' => $this->depth,
            'position' => $this->position,
            'is_active' => $this->is_active,
        ];
    }
}
