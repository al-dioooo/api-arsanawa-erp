<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\Price;

class ResolvePrice
{
    /**
     * @param  array<string, mixed>  $params
     * @return array{price: string|null}
     */
    public function execute(int $variantId, array $params): array
    {
        $on = $params['on'] ?? now()->toDateString();

        $price = Price::query()
            ->where('product_variant_id', $variantId)
            ->where('effective_from', '<=', $on)
            ->where(function ($q) use ($on): void {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $on);
            })
            ->orderByDesc('effective_from')
            ->first();

        return ['price' => $price?->price];
    }
}
