<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Price;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\ProductImage;
use App\Modules\Inventory\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExternalProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $branchId = $this->validatedBranchId($request, $companyId);
        $on = $this->validatedPriceDate($request);

        $products = Product::query()
            ->forCompany($companyId)
            ->where('status', 'active')
            ->with([
                'images',
                'variants' => fn ($query) => $query->where('is_active', true)->orderBy('sku'),
            ])
            ->orderBy('name')
            ->get();

        // Batch-resolve prices and availability for every variant up front to
        // avoid a query per variant on this polled catalog endpoint.
        $variantIds = $products->flatMap(fn (Product $product): array => $product->variants->pluck('id')->all())->all();
        $priceMap = $this->priceMap($companyId, $variantIds, $on);
        $availabilityMap = $this->availabilityMap($companyId, $variantIds, $branchId);

        $transformed = $products
            ->map(fn (Product $product): array => $this->transformProduct($product, $priceMap, $availabilityMap))
            ->filter(fn (array $product): bool => $product['variants'] !== [])
            ->values();

        return $this->success(
            ['products' => $transformed],
            __('Products retrieved.'),
        );
    }

    /**
     * Latest effective price per variant, eager-loaded, resolved in one query.
     *
     * @param  array<int, int>  $variantIds
     * @return array<int, Price>
     */
    private function priceMap(int $companyId, array $variantIds, string $on): array
    {
        if ($variantIds === []) {
            return [];
        }

        $prices = Price::query()
            ->with(['priceList', 'productUnit.images'])
            ->whereIn('product_variant_id', $variantIds)
            ->where('effective_from', '<=', $on)
            ->where(function ($query) use ($on): void {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $on);
            })
            ->whereHas('priceList', fn ($query) => $query
                ->where('company_id', $companyId)
                ->where('is_active', true))
            ->orderByDesc('effective_from')
            ->latest('id')
            ->get();

        $map = [];
        foreach ($prices as $price) {
            // Rows are ordered newest-first, so keep the first seen per variant.
            $map[$price->product_variant_id] ??= $price;
        }

        return $map;
    }

    /**
     * Availability flag per variant for the branch, resolved in one query.
     * A missing row means available.
     *
     * @param  array<int, int>  $variantIds
     * @return array<int, bool>
     */
    private function availabilityMap(int $companyId, array $variantIds, ?int $branchId): array
    {
        if ($branchId === null || $variantIds === []) {
            return [];
        }

        return DB::table('product_branch_availability')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->whereIn('product_variant_id', $variantIds)
            ->pluck('is_available', 'product_variant_id')
            ->map(fn ($isAvailable): bool => (bool) $isAvailable)
            ->all();
    }

    public function price(Request $request, int $variant): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $on = $this->validatedPriceDate($request);

        $resolvedVariant = ProductVariant::query()
            ->forCompany($companyId)
            ->where('is_active', true)
            ->findOrFail($variant);

        $price = $this->resolvePrice($companyId, $resolvedVariant->id, $on);

        return $this->success(
            [
                'product_variant_id' => $resolvedVariant->id,
                'price' => $price?->price,
                'maximum_retail_price' => $price?->maximum_retail_price,
                'currency_id' => $price?->priceList?->currency_id,
            ],
            __('Price resolved.'),
        );
    }

    /**
     * @param  array<int, Price>  $priceMap
     * @param  array<int, bool>  $availabilityMap
     */
    private function transformProduct(Product $product, array $priceMap, array $availabilityMap): array
    {
        $variants = $product->variants
            ->filter(fn (ProductVariant $variant): bool => $availabilityMap[$variant->id] ?? true)
            ->map(function (ProductVariant $variant) use ($priceMap): array {
                $price = $priceMap[$variant->id] ?? null;

                return [
                    'id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'sku' => $variant->sku,
                    'barcode' => $variant->barcode,
                    'name' => $variant->name,
                    'attributes' => $variant->attributes,
                    'price' => $price?->price,
                    'maximum_retail_price' => $price?->maximum_retail_price,
                    'currency_id' => $price?->priceList?->currency_id,
                    'product_unit' => $price?->productUnit ? [
                        'id' => $price->productUnit->id,
                        'sku' => $price->productUnit->sku,
                        'barcode' => $price->productUnit->barcode,
                        'name' => $price->productUnit->name,
                        'images' => $this->imageMetadata($price->productUnit->images),
                    ] : null,
                ];
            })
            ->values()
            ->all();

        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'attributes' => $product->attributes,
            'images' => $this->imageMetadata($product->images),
            'variants' => $variants,
        ];
    }

    private function resolvePrice(int $companyId, int $variantId, string $on): ?Price
    {
        return Price::query()
            ->with(['priceList', 'productUnit.images'])
            ->where('product_variant_id', $variantId)
            ->where('effective_from', '<=', $on)
            ->where(function ($query) use ($on): void {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $on);
            })
            ->whereHas('priceList', fn ($query) => $query
                ->where('company_id', $companyId)
                ->where('is_active', true))
            ->orderByDesc('effective_from')
            ->latest('id')
            ->first();
    }

    private function validatedBranchId(Request $request, int $companyId): ?int
    {
        $branchId = $request->query('branch_id');

        if ($branchId === null) {
            return null;
        }

        if (! ctype_digit((string) $branchId)) {
            abort(404);
        }

        $exists = DB::table('branches')
            ->where('id', (int) $branchId)
            ->where('company_id', $companyId)
            ->exists();

        abort_unless($exists, 404);

        return (int) $branchId;
    }

    private function validatedPriceDate(Request $request): string
    {
        $on = $request->query('on');

        if ($on === null) {
            return now()->toDateString();
        }

        if (! is_string($on) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $on)) {
            abort(404);
        }

        return $on;
    }

    private function imageMetadata($images): array
    {
        return $images
            ->map(fn (ProductImage $image): array => [
                'id' => $image->id,
                'url' => $image->url,
                'original_url' => $image->original_url,
                'alt_text' => $image->alt_text,
                'mime_type' => $image->mime_type,
                'size_bytes' => $image->size_bytes,
                'width' => $image->width,
                'height' => $image->height,
                'is_primary' => $image->is_primary,
                'sort_order' => $image->sort_order,
            ])
            ->values()
            ->all();
    }
}
