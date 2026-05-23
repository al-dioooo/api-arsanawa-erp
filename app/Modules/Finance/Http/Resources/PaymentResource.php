<?php

namespace App\Modules\Finance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'payment_number' => $this->payment_number,
            'partner_id' => $this->partner_id,
            'payment_type' => $this->payment_type,
            'payment_date' => $this->payment_date instanceof Carbon
                ? $this->payment_date->format('Y-m-d')
                : Carbon::parse($this->payment_date)->format('Y-m-d'),
            'payment_method' => $this->payment_method,
            'amount' => $this->amount,
            'currency_id' => $this->currency_id,
            'exchange_rate' => (float) $this->exchange_rate,
            'cash_account_id' => $this->cash_account_id,
            'status' => $this->status,
            'notes' => $this->notes,
            'journal_entry_id' => $this->journal_entry_id,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'allocations' => PaymentAllocationResource::collection($this->whenLoaded('allocations')),
        ];
    }
}
