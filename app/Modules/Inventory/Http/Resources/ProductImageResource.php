<?php

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'disk' => $this->disk,
            'path' => $this->path,
            'url' => $this->url,
            'original_url' => $this->original_url,
            'alt_text' => $this->alt_text,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'width' => $this->width,
            'height' => $this->height,
            'is_primary' => $this->is_primary,
            'sort_order' => $this->sort_order,
        ];
    }
}
