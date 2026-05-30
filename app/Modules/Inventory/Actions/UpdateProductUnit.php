<?php

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Inventory\Models\ProductUnit;
use Illuminate\Support\Facades\DB;

class UpdateProductUnit
{
    /**
     * @param  array{product_id?: int, sku?: string, barcode?: ?string, name?: ?string, variant_ids?: array<int, int>, is_active?: bool}  $data
     */
    public function execute(ProductUnit $productUnit, User $user, array $data): ProductUnit
    {
        return DB::transaction(function () use ($productUnit, $user, $data): ProductUnit {
            $productUnit->fill(collect($data)->except('variant_ids')->all());
            $productUnit->updated_by = $user->id;
            $productUnit->save();

            if (array_key_exists('variant_ids', $data)) {
                $productUnit->variants()->sync($data['variant_ids'] ?? []);
            }

            return $productUnit->load(['product.category', 'product.brand', 'variants.group.unit']);
        });
    }
}
