<?php

namespace App\Modules\Pos\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'register_id' => $this->register_id,
            'user_id' => $this->user_id,
            'status' => $this->status,
            'opened_at' => $this->opened_at?->toISOString(),
            'closed_at' => $this->closed_at?->toISOString(),
            'opening_float' => $this->opening_float,
            'expected_cash' => $this->expected_cash,
            'counted_cash' => $this->counted_cash,
            'cash_variance' => $this->cash_variance,
            'notes' => $this->notes,
        ];
    }
}
