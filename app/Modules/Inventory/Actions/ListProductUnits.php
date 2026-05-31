<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\ProductUnit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListProductUnits
{
    /**
     * @param  array{product_id?: int, category_id?: int, brand_id?: int, status?: string, search?: string, per_page?: int}  $filters
     */
    public function execute(int $companyId, array $filters = []): LengthAwarePaginator
    {
        $query = ProductUnit::query()
            ->forCompany($companyId)
            ->with(['product.category', 'product.brand', 'images', 'variants.group.unit']);

        if (isset($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (isset($filters['category_id'])) {
            $query->whereHas('product', fn (Builder $product) => $product->where('category_id', $filters['category_id']));
        }

        if (isset($filters['brand_id'])) {
            $query->whereHas('product', fn (Builder $product) => $product->where('brand_id', $filters['brand_id']));
        }

        if (isset($filters['status'])) {
            $query->where('is_active', $filters['status'] === 'active');
        }

        if (isset($filters['search']) && $filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhereHas('product', fn (Builder $product) => $product->where('name', 'like', "%{$search}%"));
            });
        }

        return $query->orderBy('sku')->paginate($filters['per_page'] ?? 15);
    }
}
