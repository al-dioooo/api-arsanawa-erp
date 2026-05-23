<?php

namespace App\Modules\Pos\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_id' => $this->sale_id,
            'product_variant_id' => $this->product_variant_id,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'discount' => $this->discount,
            'line_subtotal' => $this->line_subtotal,
            'tax_amount' => $this->tax_amount,
            'line_total' => $this->line_total,
            'tax_rate_id' => $this->tax_rate_id,
            'revenue_account_id' => $this->revenue_account_id,
            'is_giveaway' => $this->is_giveaway,
        ];
    }
}
