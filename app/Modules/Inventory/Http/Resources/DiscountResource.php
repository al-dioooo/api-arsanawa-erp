<?php

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiscountResource extends JsonResource
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
            'starting_item_number' => $this->starting_item_number,
            'multiply' => $this->multiply,
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'is_active' => $this->is_active,
            'targets' => $this->whenLoaded('targets', fn () => $this->targets->map(fn ($t): array => [
                'target_type' => $t->target_type,
                'target_id' => $t->target_id,
            ])),
            'dependencies' => $this->whenLoaded('dependencies', fn () => $this->dependencies->map(fn ($d): array => [
                'product_variant_id' => $d->product_variant_id,
                'required_quantity' => $d->required_quantity,
            ])),
            'giveaways' => $this->whenLoaded('giveaways', fn () => $this->giveaways->map(fn ($g): array => [
                'product_variant_id' => $g->product_variant_id,
                'giveaway_quantity' => $g->giveaway_quantity,
            ])),
        ];
    }
}
