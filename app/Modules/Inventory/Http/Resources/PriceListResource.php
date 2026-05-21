<?php

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriceListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'currency_id' => $this->currency_id,
            'branch_id' => $this->branch_id,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
        ];
    }
}
