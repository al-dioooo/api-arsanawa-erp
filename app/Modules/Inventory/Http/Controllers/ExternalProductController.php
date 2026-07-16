<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Http\Resources\External\ExternalPriceResource;
use App\Modules\Inventory\Http\Resources\External\ExternalProductResource;
use App\Modules\Inventory\Models\Price;
use App\Modules\Inventory\Models\Product;
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
            ->map(fn (Product $product): array => (new ExternalProductResource($product, $priceMap, $availabilityMap))->resolve($request))
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
            (new ExternalPriceResource($resolvedVariant, $price))->resolve($request),
            __('Price resolved.'),
        );
    }

    /**
     * Latest effective price for one variant on the given date.
     *
     * Deliberately not delegated to the shared ResolvePrice action: this
     * external endpoint additionally scopes the price list to the api-key
     * company and to active price lists, which the shared action does not.
     */
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
}
