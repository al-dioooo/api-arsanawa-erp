<?php

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\ProductVariant;

/**
 * Deliberate exception to the one-execute()-per-Action convention: product variants
 * are a child collection whose add/update/delete share invariants and always
 * ship together, so the lifecycle lives in one class instead of three files.
 */
class ManageVariants
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function add(Product $product, User $user, array $data): ProductVariant
    {
        return $product->variants()->create([
            'company_id' => $product->company_id,
            'sku' => $data['sku'],
            'barcode' => $data['barcode'] ?? null,
            'name' => $data['name'] ?? null,
            'attributes' => $data['attributes'] ?? null,
            'purchase_uom_id' => $data['purchase_uom_id'] ?? null,
            'purchase_conversion_factor' => $data['purchase_conversion_factor'] ?? 1,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ProductVariant $variant, User $user, array $data): ProductVariant
    {
        $fields = ['sku', 'barcode', 'name', 'attributes', 'purchase_uom_id', 'purchase_conversion_factor', 'is_active'];

        $variant->fill(array_intersect_key($data, array_flip($fields)));
        $variant->updated_by = $user->id;
        $variant->save();

        return $variant;
    }

    public function delete(ProductVariant $variant): void
    {
        $variant->delete();
    }
}
