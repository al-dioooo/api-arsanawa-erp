<?php

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RewardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'name' => $this->name,
            'calculation_type' => $this->calculation_type,
            'value' => $this->value,
            'min_quantity' => $this->min_quantity,
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'is_active' => $this->is_active,
            'targets' => $this->whenLoaded('targets', fn () => $this->targets->map(fn ($t): array => [
                'target_type' => $t->target_type,
                'target_id' => $t->target_id,
            ])),
        ];
    }
}
