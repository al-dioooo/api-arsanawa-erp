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
            ->get()
            ->map(fn (Product $product): array => $this->transformProduct($product, $companyId, $branchId, $on))
            ->filter(fn (array $product): bool => $product['variants'] !== [])
            ->values();

        return $this->success(
            ['products' => $products],
            __('Products retrieved.'),
        );
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

    private function transformProduct(Product $product, int $companyId, ?int $branchId, string $on): array
    {
        $variants = $product->variants
            ->filter(fn (ProductVariant $variant): bool => $this->variantIsAvailable($companyId, $variant->id, $branchId))
            ->map(function (ProductVariant $variant) use ($companyId, $on): array {
                $price = $this->resolvePrice($companyId, $variant->id, $on);

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

    private function variantIsAvailable(int $companyId, int $variantId, ?int $branchId): bool
    {
        if ($branchId === null) {
            return true;
        }

        $availability = DB::table('product_branch_availability')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('product_variant_id', $variantId)
            ->first();

        return $availability === null || (bool) $availability->is_available;
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
