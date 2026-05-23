<?php

namespace App\Modules\Pos\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalePromotionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_id' => $this->sale_id,
            'promotion_type' => $this->promotion_type,
            'promotion_id' => $this->promotion_id,
            'description' => $this->description,
            'amount' => $this->amount,
        ];
    }
}
