<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Inventory\Models\Brand;
use App\Modules\Inventory\Models\Category;
use App\Modules\Inventory\Models\Price;
use App\Modules\Inventory\Models\PriceList;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\ProductVariant;
use App\Modules\Inventory\Models\StockLot;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\UnitOfMeasure;
use Illuminate\Database\Seeder;

class InventoryDemoSeeder extends Seeder
{
    public function run(): void
    {
        // This seeder is designed for a demo company that must already exist.
        // It populates catalogue data, products, variants, opening stock, and pricing.

        $companyId = 1;
        $branchId = 1;
        $userId = User::first()?->id ?? 1;

        // --- Units of Measure ---
        $pcs = UnitOfMeasure::firstOrCreate(
            ['company_id' => $companyId, 'code' => 'PCS'],
            ['name' => 'Pieces', 'created_by' => $userId, 'updated_by' => $userId],
        );
        $kg = UnitOfMeasure::firstOrCreate(
            ['company_id' => $companyId, 'code' => 'KG'],
            ['name' => 'Kilograms', 'created_by' => $userId, 'updated_by' => $userId],
        );

        // --- Categories ---
        $beverages = Category::firstOrCreate(
            ['company_id' => $companyId, 'name' => 'Beverages'],
            ['created_by' => $userId, 'updated_by' => $userId],
        );
        $food = Category::firstOrCreate(
            ['company_id' => $companyId, 'name' => 'Food'],
            ['created_by' => $userId, 'updated_by' => $userId],
        );

        // --- Brand ---
        $brand = Brand::firstOrCreate(
            ['company_id' => $companyId, 'name' => 'Arsanawa'],
            ['created_by' => $userId, 'updated_by' => $userId],
        );

        // --- Products + Variants ---
        $products = [
            [
                'name' => 'Arabica Coffee Beans',
                'category' => $beverages,
                'uom' => $kg,
                'variants' => [
                    ['sku' => 'COF-ARB-250', 'name' => '250g Pack', 'cost' => 35000, 'price' => 55000, 'qty' => 100],
                    ['sku' => 'COF-ARB-500', 'name' => '500g Pack', 'cost' => 65000, 'price' => 95000, 'qty' => 50],
                ],
            ],
            [
                'name' => 'Green Tea',
                'category' => $beverages,
                'uom' => $pcs,
                'variants' => [
                    ['sku' => 'TEA-GRN-50', 'name' => '50 Bags', 'cost' => 12000, 'price' => 20000, 'qty' => 200],
                ],
            ],
            [
                'name' => 'Granola Bar',
                'category' => $food,
                'uom' => $pcs,
                'variants' => [
                    ['sku' => 'GRN-BAR-S', 'name' => 'Small', 'cost' => 5000, 'price' => 8000, 'qty' => 500],
                    ['sku' => 'GRN-BAR-L', 'name' => 'Large', 'cost' => 9000, 'price' => 14000, 'qty' => 300],
                ],
            ],
        ];

        // --- Price List ---
        $priceList = PriceList::firstOrCreate(
            ['company_id' => $companyId, 'name' => 'Default Retail'],
            ['is_default' => true, 'is_active' => true, 'created_by' => $userId, 'updated_by' => $userId],
        );

        foreach ($products as $def) {
            $product = Product::firstOrCreate(
                ['company_id' => $companyId, 'name' => $def['name']],
                [
                    'category_id' => $def['category']->id,
                    'brand_id' => $brand->id,
                    'base_uom_id' => $def['uom']->id,
                    'track_stock' => true,
                    'status' => 'active',
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ],
            );

            foreach ($def['variants'] as $v) {
                $variant = ProductVariant::firstOrCreate(
                    ['company_id' => $companyId, 'sku' => $v['sku']],
                    [
                        'product_id' => $product->id,
                        'name' => $v['name'],
                        'is_active' => true,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ],
                );

                // Opening stock receipt
                if (StockLot::where('product_variant_id', $variant->id)->doesntExist()) {
                    $lot = StockLot::create([
                        'company_id' => $companyId,
                        'branch_id' => $branchId,
                        'product_variant_id' => $variant->id,
                        'lot_number' => "OPENING-{$v['sku']}",
                        'received_quantity' => $v['qty'],
                        'remaining_quantity' => $v['qty'],
                        'unit_cost' => $v['cost'],
                        'received_at' => now()->toDateString(),
                        'status' => 'active',
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);

                    StockMovement::create([
                        'company_id' => $companyId,
                        'branch_id' => $branchId,
                        'product_variant_id' => $variant->id,
                        'stock_lot_id' => $lot->id,
                        'type' => 'receipt',
                        'quantity' => $v['qty'],
                        'unit_cost' => $v['cost'],
                        'notes' => 'Opening stock',
                        'occurred_at' => now(),
                        'created_by' => $userId,
                    ]);
                }

                // Default retail price
                if (Price::where('price_list_id', $priceList->id)->where('product_variant_id', $variant->id)->doesntExist()) {
                    Price::create([
                        'price_list_id' => $priceList->id,
                        'product_variant_id' => $variant->id,
                        'price' => $v['price'],
                        'effective_from' => now()->toDateString(),
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);
                }
            }
        }
    }
}
