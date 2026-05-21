<?php

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'category_id' => $this->category_id,
            'brand_id' => $this->brand_id,
            'base_uom_id' => $this->base_uom_id,
            'name' => $this->name,
            'description' => $this->description,
            'track_stock' => $this->track_stock,
            'attributes' => $this->attributes,
            'status' => $this->status,
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($tag): array => [
                'id' => $tag->id,
                'name' => $tag->name,
            ])),
        ];
    }
}
