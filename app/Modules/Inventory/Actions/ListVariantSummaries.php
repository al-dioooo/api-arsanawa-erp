<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\ProductVariant;
use App\Modules\Inventory\Support\VariantSummary;
use Illuminate\Support\Collection;

class ListVariantSummaries
{
    /**
     * Read contract for other modules: batch-resolve cart variants.
     *
     * Callers pass every variant id in one go rather than querying per line.
     *
     * @param  array<int, mixed>  $variantIds
     * @param  int|null  $companyId  Restrict to a company; null accepts any.
     * @return Collection<int, VariantSummary> Keyed by variant id.
     */
    public function execute(array $variantIds, ?int $companyId = null): Collection
    {
        return ProductVariant::query()
            ->when($companyId !== null, fn ($query) => $query->forCompany($companyId))
            ->whereIn('id', $variantIds)
            ->with('product')
            ->get()
            ->mapWithKeys(fn (ProductVariant $variant): array => [
                $variant->id => new VariantSummary(
                    id: $variant->id,
                    productId: $variant->product_id,
                    categoryId: $variant->product?->category_id,
                    name: $variant->name,
                    sku: $variant->sku,
                ),
            ]);
    }
}
