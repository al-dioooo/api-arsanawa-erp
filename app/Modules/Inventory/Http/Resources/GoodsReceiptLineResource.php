<?php

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class GoodsReceiptLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'goods_receipt_id' => $this->goods_receipt_id,
            'product_variant_id' => $this->product_variant_id,
            'product_unit_id' => $this->product_unit_id,
            'product_unit' => new ProductUnitResource($this->whenLoaded('productUnit')),
            'stock_lot_id' => $this->stock_lot_id,
            'stock_movement_id' => $this->stock_movement_id,
            'quantity' => $this->quantity,
            'unit_cost' => $this->unit_cost,
            'line_total' => $this->line_total,
            'lot_number' => $this->lot_number,
            'expiry_date' => $this->expiry_date instanceof Carbon
                ? $this->expiry_date->format('Y-m-d')
                : ($this->expiry_date !== null ? Carbon::parse($this->expiry_date)->format('Y-m-d') : null),
            'notes' => $this->notes,
        ];
    }
}
