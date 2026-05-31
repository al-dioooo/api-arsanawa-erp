<?php

namespace App\Modules\Pos\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'register_id' => $this->register_id,
            'cashier_shift_id' => $this->cashier_shift_id,
            'sale_number' => $this->sale_number,
            'type' => $this->type,
            'partner_id' => $this->partner_id,
            'customer_name' => $this->customer_name,
            'status' => $this->status,
            'source' => $this->source,
            'source_channel' => $this->source_channel,
            'external_reference' => $this->external_reference,
            'external_api_key_id' => $this->external_api_key_id,
            'order_date' => $this->order_date?->toDateString(),
            'fulfilment_date' => $this->fulfilment_date?->toDateString(),
            'fulfilment_time_window' => $this->fulfilment_time_window,
            'delivery_address' => $this->delivery_address,
            'currency_id' => $this->currency_id,
            'exchange_rate' => $this->exchange_rate,
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'tax_total' => $this->tax_total,
            'total' => $this->total,
            'amount_paid' => $this->amount_paid,
            'notes' => $this->notes,
            'revenue_journal_entry_id' => $this->revenue_journal_entry_id,
            'cogs_journal_entry_id' => $this->cogs_journal_entry_id,
            'completed_at' => $this->completed_at?->toISOString(),
            'lines' => SaleLineResource::collection($this->whenLoaded('lines')),
            'payments' => SalePaymentResource::collection($this->whenLoaded('payments')),
            'promotions' => SalePromotionResource::collection($this->whenLoaded('promotions')),
        ];
    }
}
