<?php

namespace App\Modules\Finance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bill_id' => $this->bill_id,
            'description' => $this->description,
            'product_variant_id' => $this->product_variant_id,
            'expense_account_id' => $this->expense_account_id,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'discount' => $this->discount,
            'line_subtotal' => $this->line_subtotal,
            'tax_amount' => $this->tax_amount,
            'withholding_amount' => $this->withholding_amount,
            'line_total' => $this->line_total,
            'tax_rate_id' => $this->tax_rate_id,
        ];
    }
}
