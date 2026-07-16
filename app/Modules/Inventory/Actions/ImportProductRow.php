<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\Brand;
use App\Modules\Inventory\Models\Category;
use App\Modules\Inventory\Models\Price;
use App\Modules\Inventory\Models\PriceList;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\ProductUnit;
use App\Modules\Inventory\Models\ProductVariant;
use App\Modules\Inventory\Models\StockLot;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\Inventory\Models\Variant;
use App\Modules\Inventory\Models\VariantGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportProductRow
{
    /**
     * Write contract for other modules: upsert one spreadsheet product row.
     *
     * Owns the whole catalog graph a row touches — unit of measure, category
     * path, brand, product, variant, product unit, variant values, price, and
     * opening stock — so importers never assemble it themselves.
     *
     * @param  array<string, mixed>  $row
     * @param  int|null  $currencyId  Resolved by the caller; Platform owns currencies.
     * @return bool True when the row counts as a created product.
     */
    public function execute(int $companyId, ?int $userId, array $row, ?int $currencyId = null): bool
    {
        $unit = UnitOfMeasure::query()->updateOrCreate(
            ['company_id' => $companyId, 'code' => Str::upper($row['base_uom_code'])],
            ['name' => Str::upper($row['base_uom_code']), 'is_active' => true, 'created_by' => $userId, 'updated_by' => $userId],
        );
        $category = $row['category_path'] ? $this->categoryPath($companyId, $row['category_path'], $userId) : null;
        $brand = $row['brand_name'] ? Brand::query()->updateOrCreate(
            ['company_id' => $companyId, 'name' => $row['brand_name']],
            ['is_active' => true, 'created_by' => $userId, 'updated_by' => $userId],
        ) : null;
        $product = Product::query()->firstOrNew([
            'company_id' => $companyId,
            'name' => $row['product_name'],
        ]);
        $productExists = $product->exists;
        $product->fill([
            'category_id' => $category?->id,
            'brand_id' => $brand?->id,
            'base_uom_id' => $unit->id,
            'description' => $row['product_description'] ?: null,
            'track_stock' => (bool) $row['track_stock'],
            'status' => $row['status'] ?: 'active',
            'created_by' => $product->created_by ?: $userId,
            'updated_by' => $userId,
        ])->save();

        $variant = ProductVariant::query()->updateOrCreate(
            ['company_id' => $companyId, 'sku' => $row['sku']],
            [
                'product_id' => $product->id,
                'name' => $row['product_unit_name'] ?: $row['sku'],
                'barcode' => $row['barcode'] ?: null,
                'is_active' => true,
                'created_by' => $userId,
                'updated_by' => $userId,
            ],
        );
        $productUnit = ProductUnit::query()->firstOrNew([
            'company_id' => $companyId,
            'sku' => $row['sku'],
        ]);
        $unitExists = $productUnit->exists;
        $productUnit->fill([
            'product_id' => $product->id,
            'barcode' => $row['barcode'] ?: null,
            'name' => $row['product_unit_name'] ?: $row['sku'],
            'is_active' => ($row['status'] ?: 'active') === 'active',
            'created_by' => $productUnit->created_by ?: $userId,
            'updated_by' => $userId,
        ])->save();
        $productUnit->variants()->sync($this->variantValues($companyId, $unit->id, $row['variant_values'] ?: '', $userId));

        if (($row['price'] ?? 0) > 0) {
            $priceList = PriceList::query()->updateOrCreate(
                ['company_id' => $companyId, 'name' => $row['price_list_name'] ?: 'Default'],
                ['currency_id' => $currencyId, 'is_default' => true, 'is_active' => true, 'created_by' => $userId, 'updated_by' => $userId],
            );
            Price::query()->updateOrCreate(
                ['price_list_id' => $priceList->id, 'product_variant_id' => $variant->id],
                [
                    'product_unit_id' => $productUnit->id,
                    'price' => $row['price'],
                    'effective_from' => $row['effective_from'] ?: now()->toDateString(),
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ],
            );
        }

        if (($row['opening_quantity'] ?? 0) > 0 && ($row['branch_code'] ?? '') !== '') {
            $branchId = DB::table('branches')->where('company_id', $companyId)->where('code', $row['branch_code'])->value('id');
            $lotNumber = $row['batch_number'] ?: 'OPENING-'.$row['sku'];
            $lot = StockLot::query()->updateOrCreate(
                ['company_id' => $companyId, 'branch_id' => $branchId, 'lot_number' => $lotNumber],
                [
                    'product_variant_id' => $variant->id,
                    'product_unit_id' => $productUnit->id,
                    'received_quantity' => $row['opening_quantity'],
                    'remaining_quantity' => $row['opening_quantity'],
                    'unit_cost' => $row['unit_cost'] ?: 0,
                    'received_at' => $row['received_at'] ?: now()->toDateString(),
                    'expiry_date' => $row['expiry_date'] ?: null,
                    'production_date' => $row['production_date'] ?: null,
                    'batch_metadata' => ['source' => 'spreadsheet_import'],
                    'status' => 'active',
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ],
            );
            StockMovement::query()->updateOrCreate(
                ['company_id' => $companyId, 'branch_id' => $branchId, 'stock_lot_id' => $lot->id, 'type' => 'receipt'],
                [
                    'product_variant_id' => $variant->id,
                    'product_unit_id' => $productUnit->id,
                    'quantity' => $row['opening_quantity'],
                    'unit_cost' => $row['unit_cost'] ?: 0,
                    'notes' => 'Spreadsheet opening stock',
                    'occurred_at' => now(),
                    'created_by' => $userId,
                ],
            );
        }

        return $productExists || ! $unitExists;
    }

    private function categoryPath(int $companyId, string $path, ?int $userId): Category
    {
        $parent = null;

        foreach (array_filter(array_map('trim', explode('>', $path))) as $position => $name) {
            $category = Category::query()->firstOrCreate(
                ['company_id' => $companyId, 'parent_id' => $parent?->id, 'name' => $name],
                ['path' => '', 'depth' => $parent ? $parent->depth + 1 : 0, 'position' => $position, 'is_active' => true, 'created_by' => $userId, 'updated_by' => $userId],
            );
            $category->forceFill([
                'path' => $parent ? "{$parent->path}.{$category->id}" : (string) $category->id,
                'depth' => $parent ? $parent->depth + 1 : 0,
                'updated_by' => $userId,
            ])->save();
            $parent = $category;
        }

        return $parent;
    }

    /**
     * @return list<int>
     */
    private function variantValues(int $companyId, int $unitId, string $value, ?int $userId): array
    {
        if ($value === '') {
            return [];
        }

        return collect(explode(';', $value))
            ->map(function (string $pair) use ($companyId, $unitId, $userId): ?int {
                [$groupName, $variantName] = array_pad(array_map('trim', explode('=', $pair, 2)), 2, null);

                if (! $groupName || ! $variantName) {
                    return null;
                }

                $group = VariantGroup::query()->updateOrCreate(
                    ['company_id' => $companyId, 'code' => Str::slug($groupName)],
                    ['unit_of_measure_id' => $unitId, 'name' => $groupName, 'is_active' => true, 'created_by' => $userId, 'updated_by' => $userId],
                );
                $variant = Variant::query()->updateOrCreate(
                    ['variant_group_id' => $group->id, 'code' => Str::slug($variantName)],
                    ['company_id' => $companyId, 'name' => $variantName, 'is_active' => true, 'created_by' => $userId, 'updated_by' => $userId],
                );

                return $variant->id;
            })
            ->filter()
            ->values()
            ->all();
    }
}
