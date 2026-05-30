<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\ProductUnit;
use App\Modules\Inventory\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

class ProductUnitStockResolver
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{product_variant_id: int, product_unit_id?: int}
     *
     * @throws ValidationException
     */
    public function resolve(int $companyId, array $data, ?int $userId = null): array
    {
        if (isset($data['product_unit_id'])) {
            $productUnit = ProductUnit::query()
                ->with('product')
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->find((int) $data['product_unit_id']);

            if (! $productUnit) {
                throw ValidationException::withMessages([
                    'product_unit_id' => ['The selected product unit is invalid.'],
                ]);
            }

            $variant = ProductVariant::query()
                ->where('company_id', $companyId)
                ->where('product_id', $productUnit->product_id)
                ->where('sku', $productUnit->sku)
                ->first();

            if (! $variant) {
                $variant = ProductVariant::create([
                    'company_id' => $companyId,
                    'product_id' => $productUnit->product_id,
                    'sku' => $productUnit->sku,
                    'barcode' => $productUnit->barcode,
                    'name' => $productUnit->name,
                    'is_active' => true,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }

            return [
                'product_variant_id' => $variant->id,
                'product_unit_id' => $productUnit->id,
            ];
        }

        if (! isset($data['product_variant_id'])) {
            throw ValidationException::withMessages([
                'product_unit_id' => ['A product unit or product variant is required.'],
            ]);
        }

        return ['product_variant_id' => (int) $data['product_variant_id']];
    }
}
