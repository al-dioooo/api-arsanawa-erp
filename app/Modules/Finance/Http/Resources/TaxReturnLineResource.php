<?php

namespace App\Modules\Finance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxReturnLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tax_return_id' => $this->tax_return_id,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'tax_amount' => $this->tax_amount,
        ];
    }
}
