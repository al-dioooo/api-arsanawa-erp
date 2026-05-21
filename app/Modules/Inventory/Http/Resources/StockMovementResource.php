<?php

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'product_variant_id' => $this->product_variant_id,
            'stock_lot_id' => $this->stock_lot_id,
            'type' => $this->type,
            'quantity' => $this->quantity,
            'unit_cost' => $this->unit_cost,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'notes' => $this->notes,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
        ];
    }
}
