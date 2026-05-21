<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Actions\CreateBrand;
use App\Modules\Inventory\Actions\CreateCategory;
use App\Modules\Inventory\Actions\CreateProduct;
use App\Modules\Inventory\Actions\CreateUnitOfMeasure;
use App\Modules\Inventory\Actions\DeleteBrand;
use App\Modules\Inventory\Actions\DeleteCategory;
use App\Modules\Inventory\Actions\DeleteProduct;
use App\Modules\Inventory\Actions\DeleteUnitOfMeasure;
use App\Modules\Inventory\Actions\GetProduct;
use App\Modules\Inventory\Actions\ListBrands;
use App\Modules\Inventory\Actions\ListCategories;
use App\Modules\Inventory\Actions\ListProducts;
use App\Modules\Inventory\Actions\ListUnitsOfMeasure;
use App\Modules\Inventory\Actions\MoveCategory;
use App\Modules\Inventory\Actions\UpdateBrand;
use App\Modules\Inventory\Actions\UpdateCategory;
use App\Modules\Inventory\Actions\UpdateProduct;
use App\Modules\Inventory\Actions\UpdateUnitOfMeasure;
use App\Modules\Inventory\Http\Requests\CreateBrandRequest;
use App\Modules\Inventory\Http\Requests\CreateCategoryRequest;
use App\Modules\Inventory\Http\Requests\CreateProductRequest;
use App\Modules\Inventory\Http\Requests\CreateUnitOfMeasureRequest;
use App\Modules\Inventory\Http\Requests\DeleteBrandRequest;
use App\Modules\Inventory\Http\Requests\DeleteCategoryRequest;
use App\Modules\Inventory\Http\Requests\DeleteProductRequest;
use App\Modules\Inventory\Http\Requests\DeleteUnitOfMeasureRequest;
use App\Modules\Inventory\Http\Requests\ListBrandsRequest;
use App\Modules\Inventory\Http\Requests\ListCategoriesRequest;
use App\Modules\Inventory\Http\Requests\ListProductsRequest;
use App\Modules\Inventory\Http\Requests\ListUnitsOfMeasureRequest;
use App\Modules\Inventory\Http\Requests\MoveCategoryRequest;
use App\Modules\Inventory\Http\Requests\UpdateBrandRequest;
use App\Modules\Inventory\Http\Requests\UpdateCategoryRequest;
use App\Modules\Inventory\Http\Requests\UpdateProductRequest;
use App\Modules\Inventory\Http\Requests\UpdateUnitOfMeasureRequest;
use App\Modules\Inventory\Http\Resources\BrandResource;
use App\Modules\Inventory\Http\Resources\CategoryResource;
use App\Modules\Inventory\Http\Resources\ProductResource;
use App\Modules\Inventory\Http\Resources\UnitOfMeasureResource;
use App\Modules\Inventory\Actions\ManageProductTags;
use App\Modules\Inventory\Actions\ManageVariants;
use App\Modules\Inventory\Actions\SetBranchAvailability;
use App\Modules\Inventory\Http\Requests\AddVariantRequest;
use App\Modules\Inventory\Http\Requests\DeleteVariantRequest;
use App\Modules\Inventory\Http\Requests\SetAvailabilityRequest;
use App\Modules\Inventory\Http\Requests\SyncTagsRequest;
use App\Modules\Inventory\Http\Requests\UpdateVariantRequest;
use App\Modules\Inventory\Http\Resources\ProductVariantResource;
use App\Modules\Inventory\Actions\GetStockValuation;
use App\Modules\Inventory\Actions\ListStockLevels;
use App\Modules\Inventory\Actions\ListStockLots;
use App\Modules\Inventory\Actions\ListStockMovements;
use App\Modules\Inventory\Actions\RecordStockAdjustment;
use App\Modules\Inventory\Actions\RecordStockIssue;
use App\Modules\Inventory\Actions\RecordStockReceipt;
use App\Modules\Inventory\Actions\RecordStockTransfer;
use App\Modules\Inventory\Http\Requests\StockAdjustmentRequest;
use App\Modules\Inventory\Http\Requests\StockIssueRequest;
use App\Modules\Inventory\Http\Requests\StockQueryRequest;
use App\Modules\Inventory\Http\Requests\StockReceiptRequest;
use App\Modules\Inventory\Http\Requests\StockTransferRequest;
use App\Modules\Inventory\Http\Resources\StockLotResource;
use App\Modules\Inventory\Http\Resources\StockMovementResource;
use App\Modules\Inventory\Http\Resources\StockTransferResource;
use App\Modules\Inventory\Actions\CreatePriceList;
use App\Modules\Inventory\Actions\SetPrice;
use App\Modules\Inventory\Actions\ResolvePrice;
use App\Modules\Inventory\Actions\CreateDiscount;
use App\Modules\Inventory\Actions\DeleteDiscount;
use App\Modules\Inventory\Actions\CreateReward;
use App\Modules\Inventory\Actions\DeleteReward;
use App\Modules\Inventory\Http\Requests\CreatePriceListRequest;
use App\Modules\Inventory\Http\Requests\SetPriceRequest;
use App\Modules\Inventory\Http\Requests\CreateDiscountRequest;
use App\Modules\Inventory\Http\Requests\ListDiscountsRequest;
use App\Modules\Inventory\Http\Requests\DeleteDiscountRequest;
use App\Modules\Inventory\Http\Requests\CreateRewardRequest;
use App\Modules\Inventory\Http\Requests\ListRewardsRequest;
use App\Modules\Inventory\Http\Requests\DeleteRewardRequest;
use App\Modules\Inventory\Http\Resources\PriceListResource;
use App\Modules\Inventory\Http\Resources\DiscountResource;
use App\Modules\Inventory\Http\Resources\RewardResource;
use App\Modules\Inventory\Models\Brand;
use App\Modules\Inventory\Models\Category;
use App\Modules\Inventory\Models\Discount;
use App\Modules\Inventory\Models\PriceList;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\ProductVariant;
use App\Modules\Inventory\Models\Reward;
use App\Modules\Inventory\Models\UnitOfMeasure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    // --- Categories -------------------------------------------------------

    public function categories(ListCategoriesRequest $request, ListCategories $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');

        return $this->success(
            ['categories' => CategoryResource::collection($action->execute($companyId))],
            __('Categories retrieved.'),
        );
    }

    public function storeCategory(CreateCategoryRequest $request, CreateCategory $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $category = $action->execute($companyId, $request->user(), $request->validated());

        return $this->success(
            ['category' => new CategoryResource($category)],
            __('Category created.'),
            201,
        );
    }

    public function showCategory(ListCategoriesRequest $request, int $category): JsonResponse
    {
        return $this->success(
            ['category' => new CategoryResource($this->resolveCategory($request, $category))],
            __('Category retrieved.'),
        );
    }

    public function updateCategory(UpdateCategoryRequest $request, UpdateCategory $action, int $category): JsonResponse
    {
        $resolved = $this->resolveCategory($request, $category);
        $updated = $action->execute($resolved, $request->user(), $request->validated());

        return $this->success(
            ['category' => new CategoryResource($updated)],
            __('Category updated.'),
        );
    }

    public function moveCategory(MoveCategoryRequest $request, MoveCategory $action, int $category): JsonResponse
    {
        $resolved = $this->resolveCategory($request, $category);
        $parentId = $request->validated('parent_id');
        $parent = $parentId !== null ? $this->resolveCategory($request, (int) $parentId) : null;

        $updated = $action->execute($resolved, $parent, $request->user());

        return $this->success(
            ['category' => new CategoryResource($updated)],
            __('Category moved.'),
        );
    }

    public function destroyCategory(DeleteCategoryRequest $request, DeleteCategory $action, int $category): JsonResponse
    {
        $resolved = $this->resolveCategory($request, $category);

        if ($resolved->children()->exists()) {
            return $this->error(__('Remove subcategories before deleting this category.'), 422);
        }

        $action->execute($resolved);

        return $this->success(null, __('Category deleted.'));
    }

    // --- Brands -----------------------------------------------------------

    public function brands(ListBrandsRequest $request, ListBrands $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');

        return $this->success(
            ['brands' => BrandResource::collection($action->execute($companyId))],
            __('Brands retrieved.'),
        );
    }

    public function storeBrand(CreateBrandRequest $request, CreateBrand $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $brand = $action->execute($companyId, $request->user(), $request->validated());

        return $this->success(
            ['brand' => new BrandResource($brand)],
            __('Brand created.'),
            201,
        );
    }

    public function showBrand(ListBrandsRequest $request, int $brand): JsonResponse
    {
        return $this->success(
            ['brand' => new BrandResource($this->resolveBrand($request, $brand))],
            __('Brand retrieved.'),
        );
    }

    public function updateBrand(UpdateBrandRequest $request, UpdateBrand $action, int $brand): JsonResponse
    {
        $resolved = $this->resolveBrand($request, $brand);
        $updated = $action->execute($resolved, $request->user(), $request->validated());

        return $this->success(
            ['brand' => new BrandResource($updated)],
            __('Brand updated.'),
        );
    }

    public function destroyBrand(DeleteBrandRequest $request, DeleteBrand $action, int $brand): JsonResponse
    {
        $action->execute($this->resolveBrand($request, $brand));

        return $this->success(null, __('Brand deleted.'));
    }

    // --- Units of measure -------------------------------------------------

    public function units(ListUnitsOfMeasureRequest $request, ListUnitsOfMeasure $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');

        return $this->success(
            ['units' => UnitOfMeasureResource::collection($action->execute($companyId))],
            __('Units of measure retrieved.'),
        );
    }

    public function storeUnit(CreateUnitOfMeasureRequest $request, CreateUnitOfMeasure $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $unit = $action->execute($companyId, $request->user(), $request->validated());

        return $this->success(
            ['unit' => new UnitOfMeasureResource($unit)],
            __('Unit of measure created.'),
            201,
        );
    }

    public function showUnit(ListUnitsOfMeasureRequest $request, int $unit): JsonResponse
    {
        return $this->success(
            ['unit' => new UnitOfMeasureResource($this->resolveUnit($request, $unit))],
            __('Unit of measure retrieved.'),
        );
    }

    public function updateUnit(UpdateUnitOfMeasureRequest $request, UpdateUnitOfMeasure $action, int $unit): JsonResponse
    {
        $resolved = $this->resolveUnit($request, $unit);
        $updated = $action->execute($resolved, $request->user(), $request->validated());

        return $this->success(
            ['unit' => new UnitOfMeasureResource($updated)],
            __('Unit of measure updated.'),
        );
    }

    public function destroyUnit(DeleteUnitOfMeasureRequest $request, DeleteUnitOfMeasure $action, int $unit): JsonResponse
    {
        $action->execute($this->resolveUnit($request, $unit));

        return $this->success(null, __('Unit of measure deleted.'));
    }

    // --- Products ---------------------------------------------------------

    public function products(ListProductsRequest $request, ListProducts $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $paginator = $action->execute($companyId, $request->validated());

        return $this->success(
            [
                'products' => ProductResource::collection($paginator->getCollection()),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
            __('Products retrieved.'),
        );
    }

    public function storeProduct(CreateProductRequest $request, CreateProduct $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $product = $action->execute($companyId, $request->user(), $request->validated());

        return $this->success(
            ['product' => new ProductResource($product)],
            __('Product created.'),
            201,
        );
    }

    public function showProduct(ListProductsRequest $request, GetProduct $action, int $product): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');

        return $this->success(
            ['product' => new ProductResource($action->execute($product, $companyId))],
            __('Product retrieved.'),
        );
    }

    public function updateProduct(UpdateProductRequest $request, UpdateProduct $action, int $product): JsonResponse
    {
        $resolved = $this->resolveProduct($request, $product);
        $updated = $action->execute($resolved, $request->user(), $request->validated());

        return $this->success(
            ['product' => new ProductResource($updated)],
            __('Product updated.'),
        );
    }

    public function destroyProduct(DeleteProductRequest $request, DeleteProduct $action, int $product): JsonResponse
    {
        $action->execute($this->resolveProduct($request, $product));

        return $this->success(null, __('Product deleted.'));
    }

    // --- Variants ----------------------------------------------------------

    public function storeVariant(AddVariantRequest $request, ManageVariants $action, int $product): JsonResponse
    {
        $resolved = $this->resolveProduct($request, $product);
        $variant = $action->add($resolved, $request->user(), $request->validated());

        return $this->success(
            ['variant' => new ProductVariantResource($variant)],
            __('Variant created.'),
            201,
        );
    }

    public function updateVariant(UpdateVariantRequest $request, ManageVariants $action, int $product, int $variant): JsonResponse
    {
        $resolved = $this->resolveProduct($request, $product);
        $resolvedVariant = $this->resolveVariant($resolved, $variant);
        $updated = $action->update($resolvedVariant, $request->user(), $request->validated());

        return $this->success(
            ['variant' => new ProductVariantResource($updated)],
            __('Variant updated.'),
        );
    }

    public function destroyVariant(DeleteVariantRequest $request, ManageVariants $action, int $product, int $variant): JsonResponse
    {
        $resolved = $this->resolveProduct($request, $product);
        $action->delete($this->resolveVariant($resolved, $variant));

        return $this->success(null, __('Variant deleted.'));
    }

    // --- Tags --------------------------------------------------------------

    public function syncTags(SyncTagsRequest $request, ManageProductTags $action, int $product): JsonResponse
    {
        $resolved = $this->resolveProduct($request, $product);
        $updated = $action->execute($resolved, $request->validated('tags'));

        return $this->success(
            ['product' => new ProductResource($updated)],
            __('Tags synced.'),
        );
    }

    // --- Branch availability -----------------------------------------------

    public function setAvailability(SetAvailabilityRequest $request, SetBranchAvailability $action, int $product, int $variant): JsonResponse
    {
        $resolved = $this->resolveProduct($request, $product);
        $resolvedVariant = $this->resolveVariant($resolved, $variant);
        $action->execute($resolvedVariant, $request->user(), $request->validated());

        return $this->success(null, __('Availability updated.'));
    }

    // --- Stock ------------------------------------------------------------

    public function storeReceipt(StockReceiptRequest $request, RecordStockReceipt $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $lot = $action->execute($companyId, $request->user(), $request->validated());

        return $this->success(
            ['lot' => new StockLotResource($lot)],
            __('Stock receipt recorded.'),
            201,
        );
    }

    public function storeIssue(StockIssueRequest $request, RecordStockIssue $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $action->execute($companyId, $request->user(), $request->validated());

        return $this->success(null, __('Stock issue recorded.'));
    }

    public function storeAdjustment(StockAdjustmentRequest $request, RecordStockAdjustment $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $action->execute($companyId, $request->user(), $request->validated());

        return $this->success(null, __('Stock adjustment recorded.'));
    }

    public function storeTransfer(StockTransferRequest $request, RecordStockTransfer $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $transfer = $action->execute($companyId, $request->user(), $request->validated());

        return $this->success(
            ['transfer' => new StockTransferResource($transfer)],
            __('Stock transfer recorded.'),
            201,
        );
    }

    public function stockLevels(StockQueryRequest $request, ListStockLevels $action): JsonResponse
    {
        return $this->success(
            $action->execute($request->validated()),
            __('Stock levels retrieved.'),
        );
    }

    public function stockLots(StockQueryRequest $request, ListStockLots $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');

        return $this->success(
            ['lots' => StockLotResource::collection($action->execute($companyId, $request->validated()))],
            __('Stock lots retrieved.'),
        );
    }

    public function stockMovements(StockQueryRequest $request, ListStockMovements $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $paginator = $action->execute($companyId, $request->validated());

        return $this->success(
            [
                'movements' => StockMovementResource::collection($paginator->getCollection()),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
            __('Stock movements retrieved.'),
        );
    }

    public function stockValuation(StockQueryRequest $request, GetStockValuation $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');

        return $this->success(
            $action->execute($companyId, $request->validated()),
            __('Stock valuation retrieved.'),
        );
    }

    // --- Pricing -----------------------------------------------------------

    public function priceLists(ListProductsRequest $request): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $lists = PriceList::where('company_id', $companyId)->get();

        return $this->success(
            ['price_lists' => PriceListResource::collection($lists)],
            __('Price lists retrieved.'),
        );
    }

    public function storePriceList(CreatePriceListRequest $request, CreatePriceList $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $priceList = $action->execute($companyId, $request->user(), $request->validated());

        return $this->success(
            ['price_list' => new PriceListResource($priceList)],
            __('Price list created.'),
            201,
        );
    }

    public function setPrice(SetPriceRequest $request, SetPrice $action, int $priceList): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $resolved = PriceList::where('company_id', $companyId)->findOrFail($priceList);
        $price = $action->execute($resolved, $request->user(), $request->validated());

        return $this->success(
            ['price' => $price],
            __('Price set.'),
        );
    }

    public function resolvePrice(StockQueryRequest $request, ResolvePrice $action, int $product, int $variant): JsonResponse
    {
        $resolved = $this->resolveProduct($request, $product);
        $resolvedVariant = $this->resolveVariant($resolved, $variant);

        return $this->success(
            $action->execute($resolvedVariant->id, $request->query()),
            __('Price resolved.'),
        );
    }

    // --- Discounts ---------------------------------------------------------

    public function discounts(ListDiscountsRequest $request): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $discounts = Discount::where('company_id', $companyId)
            ->with(['targets', 'dependencies', 'giveaways'])
            ->get();

        return $this->success(
            ['discounts' => DiscountResource::collection($discounts)],
            __('Discounts retrieved.'),
        );
    }

    public function showDiscount(ListDiscountsRequest $request, int $discount): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $resolved = Discount::where('company_id', $companyId)
            ->with(['targets', 'dependencies', 'giveaways'])
            ->findOrFail($discount);

        return $this->success(
            ['discount' => new DiscountResource($resolved)],
            __('Discount retrieved.'),
        );
    }

    public function storeDiscount(CreateDiscountRequest $request, CreateDiscount $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $discount = $action->execute($companyId, $request->user(), $request->validated());

        return $this->success(
            ['discount' => new DiscountResource($discount)],
            __('Discount created.'),
            201,
        );
    }

    public function destroyDiscount(DeleteDiscountRequest $request, DeleteDiscount $action, int $discount): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $resolved = Discount::where('company_id', $companyId)->findOrFail($discount);
        $action->execute($resolved);

        return $this->success(null, __('Discount deleted.'));
    }

    // --- Rewards -----------------------------------------------------------

    public function rewards(ListRewardsRequest $request): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $rewards = Reward::where('company_id', $companyId)
            ->with('targets')
            ->get();

        return $this->success(
            ['rewards' => RewardResource::collection($rewards)],
            __('Rewards retrieved.'),
        );
    }

    public function storeReward(CreateRewardRequest $request, CreateReward $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $reward = $action->execute($companyId, $request->user(), $request->validated());

        return $this->success(
            ['reward' => new RewardResource($reward)],
            __('Reward created.'),
            201,
        );
    }

    public function destroyReward(DeleteRewardRequest $request, DeleteReward $action, int $reward): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $resolved = Reward::where('company_id', $companyId)->findOrFail($reward);
        $action->execute($resolved);

        return $this->success(null, __('Reward deleted.'));
    }

    // --- Resolution helpers ----------------------------------------------

    private function resolveProduct(Request $request, int $id): Product
    {
        return Product::query()
            ->where('company_id', $request->attributes->get('active_company_id'))
            ->findOrFail($id);
    }

    private function resolveCategory(Request $request, int $id): Category
    {
        return Category::query()
            ->where('company_id', $request->attributes->get('active_company_id'))
            ->findOrFail($id);
    }

    private function resolveBrand(Request $request, int $id): Brand
    {
        return Brand::query()
            ->where('company_id', $request->attributes->get('active_company_id'))
            ->findOrFail($id);
    }

    private function resolveUnit(Request $request, int $id): UnitOfMeasure
    {
        return UnitOfMeasure::query()
            ->where('company_id', $request->attributes->get('active_company_id'))
            ->findOrFail($id);
    }

    private function resolveVariant(Product $product, int $id): ProductVariant
    {
        return $product->variants()->findOrFail($id);
    }
}
