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

    /**
     * Resolve the effective price for every priced variant in the company in
     * one query, so catalog-wide consumers (POS register) avoid a request per
     * variant.
     *
     * @param  array<string, mixed>  $params
     * @return array{prices: array<int, array{product_variant_id: int, price: string}>}
     */
    public function executeForCompany(int $companyId, array $params): array
    {
        $on = $params['on'] ?? now()->toDateString();

        $prices = Price::query()
            ->whereHas('variant', fn ($q) => $q->where('company_id', $companyId))
            ->where('effective_from', '<=', $on)
            ->where(function ($q) use ($on): void {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $on);
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get(['id', 'product_variant_id', 'price']);

        $resolved = [];
        foreach ($prices as $price) {
            // Rows are ordered newest-first, so keep the first seen per variant.
            $resolved[$price->product_variant_id] ??= [
                'product_variant_id' => $price->product_variant_id,
                'price' => $price->price,
            ];
        }

        return ['prices' => array_values($resolved)];
    }
}
