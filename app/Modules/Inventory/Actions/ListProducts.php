<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListProducts
{
    /**
     * @param  array{category_id?: int, brand_id?: int, status?: string, search?: string, per_page?: int}  $filters
     */
    public function execute(int $companyId, array $filters = []): LengthAwarePaginator
    {
        $query = Product::query()
            ->forCompany($companyId)
            ->with(['category', 'brand', 'baseUom', 'variants', 'productUnits.variants.group.unit', 'tags']);

        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (isset($filters['brand_id'])) {
            $query->where('brand_id', $filters['brand_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['search']) && $filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhereHas('variants', fn (Builder $v) => $v->where('sku', 'like', "%{$search}%"));
            });
        }

        return $query->orderBy('name')->paginate($filters['per_page'] ?? 15);
    }
}
