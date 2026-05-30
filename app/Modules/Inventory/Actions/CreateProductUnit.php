<?php

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Inventory\Models\ProductUnit;
use Illuminate\Support\Facades\DB;

class CreateProductUnit
{
    /**
     * @param  array{product_id: int, sku: string, barcode?: ?string, name?: ?string, variant_ids?: array<int, int>, is_active?: bool}  $data
     */
    public function execute(int $companyId, User $user, array $data): ProductUnit
    {
        return DB::transaction(function () use ($companyId, $user, $data): ProductUnit {
            $productUnit = ProductUnit::create([
                'company_id' => $companyId,
                'product_id' => $data['product_id'],
                'sku' => $data['sku'],
                'barcode' => $data['barcode'] ?? null,
                'name' => $data['name'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $productUnit->variants()->sync($data['variant_ids'] ?? []);

            return $productUnit->load(['product.category', 'product.brand', 'variants.group.unit']);
        });
    }
}
