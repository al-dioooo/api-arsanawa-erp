<?php

namespace Database\Seeders;

use App\Models\User;
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
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Company;
use Illuminate\Database\Seeder;

class SekaloriInventoryDemoSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('slug', 'sekalori')->first();
        $branch = $company ? Branch::query()->where('company_id', $company->id)->orderByDesc('is_primary')->orderBy('id')->first() : null;
        $owner = User::query()->where('username', 'sekalori')->first() ?? User::query()->first();

        if ($company === null || $branch === null || $owner === null) {
            return;
        }

        $uoms = [
            'PAX' => $this->unit($company->id, 'PAX', 'Pax', $owner->id),
            'BOX' => $this->unit($company->id, 'BOX', 'Box', $owner->id),
            'TRAY' => $this->unit($company->id, 'TRAY', 'Tray', $owner->id),
            'PCS' => $this->unit($company->id, 'PCS', 'Pieces', $owner->id),
        ];

        $catering = $this->category($company->id, 'Catering', null, 10, $owner->id);
        $nasiBox = $this->category($company->id, 'Nasi Box', $catering, 10, $owner->id);
        $regular = $this->category($company->id, 'Regular', $nasiBox, 10, $owner->id);
        $premium = $this->category($company->id, 'Premium', $nasiBox, 20, $owner->id);
        $indonesianLocal = $this->category($company->id, 'Indonesian Local', $catering, 30, $owner->id);
        $western = $this->category($company->id, 'Western', $catering, 40, $owner->id);
        $japanese = $this->category($company->id, 'Japanese', $catering, 50, $owner->id);
        $beverage = $this->category($company->id, 'Beverage', null, 20, $owner->id);
        $bottledDrink = $this->category($company->id, 'Bottled Drink', $beverage, 10, $owner->id);
        $addOn = $this->category($company->id, 'Add-on', null, 30, $owner->id);
        $snack = $this->category($company->id, 'Snack', $addOn, 10, $owner->id);

        $brand = Brand::query()->updateOrCreate(
            ['company_id' => $company->id, 'name' => 'SEKALORI'],
            ['is_active' => true, 'created_by' => $owner->id, 'updated_by' => $owner->id],
        );

        $groups = [
            'package-size' => $this->variantGroup($company->id, $uoms['PAX']->id, 'Package Size', 'package-size', 'Serving package size', $owner->id),
            'rice-type' => $this->variantGroup($company->id, $uoms['PAX']->id, 'Rice Type', 'rice-type', 'Rice base for catering menus', $owner->id),
            'protein' => $this->variantGroup($company->id, $uoms['PAX']->id, 'Protein', 'protein', 'Main protein option', $owner->id),
            'spice-level' => $this->variantGroup($company->id, $uoms['PAX']->id, 'Spice Level', 'spice-level', 'Spice preference', $owner->id),
        ];

        $variants = [
            '25-pax' => $this->variant($company->id, $groups['package-size']->id, '25 Pax', '25-pax', 10, $owner->id),
            '50-pax' => $this->variant($company->id, $groups['package-size']->id, '50 Pax', '50-pax', 20, $owner->id),
            'nasi-putih' => $this->variant($company->id, $groups['rice-type']->id, 'Nasi Putih', 'nasi-putih', 10, $owner->id),
            'nasi-liwet' => $this->variant($company->id, $groups['rice-type']->id, 'Nasi Liwet', 'nasi-liwet', 20, $owner->id),
            'ayam' => $this->variant($company->id, $groups['protein']->id, 'Ayam', 'ayam', 10, $owner->id),
            'daging' => $this->variant($company->id, $groups['protein']->id, 'Daging', 'daging', 20, $owner->id),
            'mild' => $this->variant($company->id, $groups['spice-level']->id, 'Mild', 'mild', 10, $owner->id),
            'spicy' => $this->variant($company->id, $groups['spice-level']->id, 'Spicy', 'spicy', 20, $owner->id),
        ];

        $products = [
            [
                'name' => 'Nasi Box Regular',
                'category' => $regular,
                'uom' => $uoms['BOX'],
                'units' => [
                    ['sku' => 'SKL-NB-REG-25-AYM', 'name' => 'Nasi Box Regular 25 Pax Ayam', 'price' => 875000, 'cost' => 550000, 'qty' => 40, 'variants' => ['25-pax', 'nasi-putih', 'ayam', 'mild']],
                    ['sku' => 'SKL-NB-REG-50-AYM', 'name' => 'Nasi Box Regular 50 Pax Ayam', 'price' => 1700000, 'cost' => 1050000, 'qty' => 20, 'variants' => ['50-pax', 'nasi-putih', 'ayam', 'mild']],
                ],
            ],
            [
                'name' => 'Nasi Box Premium',
                'category' => $premium,
                'uom' => $uoms['BOX'],
                'units' => [
                    ['sku' => 'SKL-NB-PRM-25-DAG', 'name' => 'Nasi Box Premium 25 Pax Daging', 'price' => 1250000, 'cost' => 760000, 'qty' => 25, 'variants' => ['25-pax', 'nasi-liwet', 'daging', 'spicy']],
                    ['sku' => 'SKL-NB-PRM-50-DAG', 'name' => 'Nasi Box Premium 50 Pax Daging', 'price' => 2400000, 'cost' => 1450000, 'qty' => 12, 'variants' => ['50-pax', 'nasi-liwet', 'daging', 'spicy']],
                ],
            ],
            [
                'name' => 'Indonesian Local Bundle',
                'category' => $indonesianLocal,
                'uom' => $uoms['BOX'],
                'units' => [
                    ['sku' => 'SKL-BND-IDN', 'name' => 'Indonesian Local Catering Bundle', 'price' => 100000, 'cost' => 65000, 'qty' => 80, 'variants' => []],
                    ['sku' => 'SKL-IDN-CMP-01', 'name' => 'Nasi Uduk Component', 'price' => 18000, 'cost' => 9000, 'qty' => 120, 'variants' => []],
                    ['sku' => 'SKL-IDN-CMP-02', 'name' => 'Ayam Bakar Component', 'price' => 42000, 'cost' => 26000, 'qty' => 90, 'variants' => []],
                    ['sku' => 'SKL-IDN-CMP-03', 'name' => 'Sayur Asem Component', 'price' => 15000, 'cost' => 7000, 'qty' => 100, 'variants' => []],
                ],
            ],
            [
                'name' => 'Western Bundle',
                'category' => $western,
                'uom' => $uoms['BOX'],
                'units' => [
                    ['sku' => 'SKL-BND-WST', 'name' => 'Western Catering Bundle', 'price' => 125000, 'cost' => 78000, 'qty' => 70, 'variants' => []],
                    ['sku' => 'SKL-WST-CMP-01', 'name' => 'Roasted Chicken Component', 'price' => 52000, 'cost' => 31000, 'qty' => 90, 'variants' => []],
                    ['sku' => 'SKL-WST-CMP-02', 'name' => 'Mashed Potato Component', 'price' => 24000, 'cost' => 11000, 'qty' => 110, 'variants' => []],
                    ['sku' => 'SKL-WST-CMP-03', 'name' => 'Garden Salad Component', 'price' => 22000, 'cost' => 10000, 'qty' => 100, 'variants' => []],
                ],
            ],
            [
                'name' => 'Japanese Bundle',
                'category' => $japanese,
                'uom' => $uoms['BOX'],
                'units' => [
                    ['sku' => 'SKL-BND-JPN', 'name' => 'Japanese Catering Bundle', 'price' => 150000, 'cost' => 92000, 'qty' => 60, 'variants' => []],
                    ['sku' => 'SKL-JPN-CMP-01', 'name' => 'Chicken Katsu Component', 'price' => 58000, 'cost' => 34000, 'qty' => 80, 'variants' => []],
                    ['sku' => 'SKL-JPN-CMP-02', 'name' => 'Sushi Roll Component', 'price' => 46000, 'cost' => 26000, 'qty' => 70, 'variants' => []],
                    ['sku' => 'SKL-JPN-CMP-03', 'name' => 'Miso Soup Component', 'price' => 18000, 'cost' => 8000, 'qty' => 100, 'variants' => []],
                ],
            ],
            [
                'name' => 'Mineral Water Bottle',
                'category' => $bottledDrink,
                'uom' => $uoms['PCS'],
                'units' => [
                    ['sku' => 'SKL-AIR-600', 'name' => 'Mineral Water 600ml', 'price' => 5000, 'cost' => 2500, 'qty' => 300, 'variants' => []],
                ],
            ],
            [
                'name' => 'Snack Box Add-on',
                'category' => $snack,
                'uom' => $uoms['BOX'],
                'units' => [
                    ['sku' => 'SKL-SNB-REG', 'name' => 'Snack Box Regular', 'price' => 22000, 'cost' => 13000, 'qty' => 150, 'variants' => []],
                ],
            ],
        ];

        $priceList = PriceList::query()->updateOrCreate(
            ['company_id' => $company->id, 'name' => 'SEKALORI Retail'],
            ['is_default' => true, 'is_active' => true, 'created_by' => $owner->id, 'updated_by' => $owner->id],
        );

        foreach ($products as $definition) {
            $product = Product::query()->updateOrCreate(
                ['company_id' => $company->id, 'name' => $definition['name']],
                [
                    'category_id' => $definition['category']->id,
                    'brand_id' => $brand->id,
                    'base_uom_id' => $definition['uom']->id,
                    'track_stock' => true,
                    'status' => 'active',
                    'created_by' => $owner->id,
                    'updated_by' => $owner->id,
                ],
            );

            foreach ($definition['units'] as $unitDefinition) {
                $legacyVariant = ProductVariant::query()->updateOrCreate(
                    ['company_id' => $company->id, 'sku' => $unitDefinition['sku']],
                    [
                        'product_id' => $product->id,
                        'name' => $unitDefinition['name'],
                        'is_active' => true,
                        'created_by' => $owner->id,
                        'updated_by' => $owner->id,
                    ],
                );

                $productUnit = ProductUnit::query()->updateOrCreate(
                    ['company_id' => $company->id, 'sku' => $unitDefinition['sku']],
                    [
                        'product_id' => $product->id,
                        'name' => $unitDefinition['name'],
                        'is_active' => true,
                        'created_by' => $owner->id,
                        'updated_by' => $owner->id,
                    ],
                );

                $productUnit->variants()->sync(
                    collect($unitDefinition['variants'])->map(fn (string $code): int => $variants[$code]->id)->all(),
                );

                Price::query()->updateOrCreate(
                    ['price_list_id' => $priceList->id, 'product_variant_id' => $legacyVariant->id],
                    [
                        'product_unit_id' => $productUnit->id,
                        'price' => $unitDefinition['price'],
                        'effective_from' => '2026-01-01',
                        'created_by' => $owner->id,
                        'updated_by' => $owner->id,
                    ],
                );

                $lot = StockLot::query()->updateOrCreate(
                    ['company_id' => $company->id, 'branch_id' => $branch->id, 'lot_number' => "OPENING-{$unitDefinition['sku']}"],
                    [
                        'product_variant_id' => $legacyVariant->id,
                        'product_unit_id' => $productUnit->id,
                        'received_quantity' => $unitDefinition['qty'],
                        'remaining_quantity' => $unitDefinition['qty'],
                        'unit_cost' => $unitDefinition['cost'],
                        'received_at' => now()->toDateString(),
                        'status' => 'active',
                        'created_by' => $owner->id,
                        'updated_by' => $owner->id,
                    ],
                );

                StockMovement::query()->updateOrCreate(
                    ['company_id' => $company->id, 'branch_id' => $branch->id, 'stock_lot_id' => $lot->id, 'type' => 'receipt'],
                    [
                        'product_variant_id' => $legacyVariant->id,
                        'product_unit_id' => $productUnit->id,
                        'quantity' => $unitDefinition['qty'],
                        'unit_cost' => $unitDefinition['cost'],
                        'notes' => 'SEKALORI opening stock',
                        'occurred_at' => now(),
                        'created_by' => $owner->id,
                    ],
                );
            }
        }
    }

    private function unit(int $companyId, string $code, string $name, int $userId): UnitOfMeasure
    {
        return UnitOfMeasure::query()->updateOrCreate(
            ['company_id' => $companyId, 'code' => $code],
            ['name' => $name, 'is_active' => true, 'created_by' => $userId, 'updated_by' => $userId],
        );
    }

    private function category(int $companyId, string $name, ?Category $parent, int $position, int $userId): Category
    {
        $category = Category::query()->firstOrCreate(
            ['company_id' => $companyId, 'parent_id' => $parent?->id, 'name' => $name],
            ['path' => '', 'depth' => 0, 'position' => $position, 'is_active' => true, 'created_by' => $userId, 'updated_by' => $userId],
        );

        $category->forceFill([
            'path' => $parent ? "{$parent->path}.{$category->id}" : (string) $category->id,
            'depth' => $parent ? $parent->depth + 1 : 0,
            'position' => $position,
            'is_active' => true,
            'updated_by' => $userId,
        ])->save();

        return $category;
    }

    private function variantGroup(int $companyId, int $unitId, string $name, string $code, string $description, int $userId): VariantGroup
    {
        return VariantGroup::query()->updateOrCreate(
            ['company_id' => $companyId, 'code' => $code],
            [
                'unit_of_measure_id' => $unitId,
                'name' => $name,
                'description' => $description,
                'is_active' => true,
                'created_by' => $userId,
                'updated_by' => $userId,
            ],
        );
    }

    private function variant(int $companyId, int $groupId, string $name, string $code, int $position, int $userId): Variant
    {
        return Variant::query()->updateOrCreate(
            ['variant_group_id' => $groupId, 'code' => $code],
            [
                'company_id' => $companyId,
                'name' => $name,
                'position' => $position,
                'is_active' => true,
                'created_by' => $userId,
                'updated_by' => $userId,
            ],
        );
    }
}
