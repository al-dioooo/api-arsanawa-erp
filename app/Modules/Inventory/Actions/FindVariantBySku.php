<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\ProductVariant;
use App\Modules\Inventory\Support\VariantSummary;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class FindVariantBySku
{
    /**
     * Read contract for other modules: resolve a company's variant by SKU.
     *
     * @param  bool  $mustExist  Throw instead of returning null when the SKU is unknown.
     *
     * @throws ModelNotFoundException
     */
    public function execute(int $companyId, string $sku, bool $mustExist = false): ?VariantSummary
    {
        $query = ProductVariant::query()
            ->forCompany($companyId)
            ->where('sku', $sku)
            ->with('product');

        $variant = $mustExist ? $query->firstOrFail() : $query->first();

        if (! $variant) {
            return null;
        }

        return new VariantSummary(
            id: $variant->id,
            productId: $variant->product_id,
            categoryId: $variant->product?->category_id,
            name: $variant->name,
            sku: $variant->sku,
        );
    }
}
