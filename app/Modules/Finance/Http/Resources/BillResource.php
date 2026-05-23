<?php

namespace App\Modules\Finance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class BillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'bill_number' => $this->bill_number,
            'partner_id' => $this->partner_id,
            'currency_id' => $this->currency_id,
            'exchange_rate' => (float) $this->exchange_rate,
            'bill_date' => $this->bill_date instanceof Carbon
                ? $this->bill_date->format('Y-m-d')
                : Carbon::parse($this->bill_date)->format('Y-m-d'),
            'due_date' => $this->due_date instanceof Carbon
                ? $this->due_date->format('Y-m-d')
                : Carbon::parse($this->due_date)->format('Y-m-d'),
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'tax_total' => $this->tax_total,
            'withholding_total' => $this->withholding_total,
            'total' => $this->total,
            'amount_paid' => $this->amount_paid,
            'notes' => $this->notes,
            'journal_entry_id' => $this->journal_entry_id,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'lines' => BillLineResource::collection($this->whenLoaded('lines')),
        ];
    }
}
