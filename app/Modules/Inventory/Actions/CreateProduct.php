<?php

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Inventory\Models\Product;
use Illuminate\Support\Facades\DB;

class CreateProduct
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(int $companyId, User $user, array $data): Product
    {
        return DB::transaction(function () use ($companyId, $user, $data): Product {
            $product = Product::create([
                'company_id' => $companyId,
                'category_id' => $data['category_id'] ?? null,
                'brand_id' => $data['brand_id'] ?? null,
                'base_uom_id' => $data['base_uom_id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'track_stock' => $data['track_stock'] ?? true,
                'attributes' => $data['attributes'] ?? null,
                'status' => $data['status'] ?? 'active',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            foreach ($data['variants'] ?? [] as $variant) {
                $product->variants()->create([
                    'company_id' => $companyId,
                    'sku' => $variant['sku'],
                    'barcode' => $variant['barcode'] ?? null,
                    'name' => $variant['name'] ?? null,
                    'attributes' => $variant['attributes'] ?? null,
                    'purchase_uom_id' => $variant['purchase_uom_id'] ?? null,
                    'purchase_conversion_factor' => $variant['purchase_conversion_factor'] ?? 1,
                    'is_active' => $variant['is_active'] ?? true,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
            }

            return $product->load(['category', 'brand', 'baseUom', 'variants', 'productUnits.variants.group.unit', 'tags']);
        });
    }
}
