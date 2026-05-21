<?php

namespace App\Modules\Partners\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartnerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'type' => $this->type,
            'name' => $this->name,
            'code' => $this->code,
            'email' => $this->email,
            'phone' => $this->phone,
            'tax_identifier' => $this->tax_identifier,
            'national_id' => $this->national_id,
            'credit_limit' => $this->credit_limit,
            'transaction_limit' => $this->transaction_limit,
            'status' => $this->status,
            'notes' => $this->notes,
            'contacts' => PartnerContactResource::collection($this->whenLoaded('contacts')),
            'addresses' => PartnerAddressResource::collection($this->whenLoaded('addresses')),
        ];
    }
}
