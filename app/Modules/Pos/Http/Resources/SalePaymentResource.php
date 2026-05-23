<?php

namespace App\Modules\Pos\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalePaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_id' => $this->sale_id,
            'method' => $this->method,
            'amount' => $this->amount,
            'reference' => $this->reference,
            'paid_at' => $this->paid_at?->toISOString(),
        ];
    }
}
