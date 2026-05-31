<?php

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockLotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'product_variant_id' => $this->product_variant_id,
            'product_unit_id' => $this->product_unit_id,
            'product_unit' => new ProductUnitResource($this->whenLoaded('productUnit')),
            'lot_number' => $this->lot_number,
            'received_quantity' => $this->received_quantity,
            'remaining_quantity' => $this->remaining_quantity,
            'unit_cost' => $this->unit_cost,
            'received_at' => $this->received_at?->toDateString(),
            'expiry_date' => $this->expiry_date?->toDateString(),
            'production_date' => $this->production_date?->toDateString(),
            'batch_metadata' => $this->batch_metadata,
            'status' => $this->status,
        ];
    }
}
