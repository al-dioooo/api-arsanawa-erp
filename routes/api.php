<?php

use App\Modules\Authentication\Http\Controllers\AuthenticationController;
use App\Modules\Identity\Http\Controllers\IdentityController;
use App\Modules\Inventory\Http\Controllers\InventoryController;
use App\Modules\Organization\Http\Controllers\ModuleRegistryController;
use App\Modules\Organization\Http\Controllers\OrganizationController;
use App\Modules\Partners\Http\Controllers\PartnersController;
use App\Modules\Platform\Http\Controllers\PlatformController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')
    ->name('api.v1.auth.')
    ->group(function (): void {
        Route::post('login', [AuthenticationController::class, 'login'])
            ->middleware('throttle:login')
            ->name('login');

        Route::post('forgot-password', [AuthenticationController::class, 'forgotPassword'])
            ->middleware('throttle:password-reset')
            ->name('forgot-password');

        Route::post('reset-password', [AuthenticationController::class, 'resetPassword'])
            ->middleware('throttle:password-reset')
            ->name('reset-password');

        Route::middleware(['auth:api', 'organization.company-context'])->group(function (): void {
            Route::get('me', [AuthenticationController::class, 'me'])->name('me');
            Route::post('logout', [AuthenticationController::class, 'logout'])->name('logout');
            Route::post('refresh', [AuthenticationController::class, 'refresh'])->name('refresh');
        });
    });

Route::middleware(['auth:api', 'organization.company-context'])
    ->prefix('v1')
    ->name('api.v1.')
    ->group(function (): void {
        Route::get('modules', [ModuleRegistryController::class, 'index'])->name('modules.index');
    });

Route::middleware(['auth:api', 'organization.company-context'])
    ->prefix('v1/identity')
    ->name('api.v1.identity.')
    ->group(function (): void {
        Route::get('profile', [IdentityController::class, 'profile'])->name('profile.show');
        Route::patch('profile', [IdentityController::class, 'updateProfile'])->name('profile.update');
        Route::get('users/{id}', [IdentityController::class, 'show'])->name('users.show');
    });

Route::middleware(['auth:api', 'organization.company-context'])
    ->prefix('v1/organization')
    ->name('api.v1.organization.')
    ->group(function (): void {
        Route::get('context', [OrganizationController::class, 'context'])->name('context.show');
        Route::get('companies', [OrganizationController::class, 'index'])->name('companies.index');
        Route::post('companies', [OrganizationController::class, 'store'])->name('companies.store');
        Route::get('companies/{company}/branches', [OrganizationController::class, 'branches'])->name('companies.branches.index');
        Route::post('companies/{company}/branches', [OrganizationController::class, 'storeBranch'])->name('companies.branches.store');
        Route::get('companies/{company}/memberships', [OrganizationController::class, 'memberships'])->name('companies.memberships.index');
        Route::post('companies/{company}/memberships', [OrganizationController::class, 'storeMembership'])->name('companies.memberships.store');
        Route::get('companies/{company}/entitlements', [OrganizationController::class, 'entitlements'])->name('companies.entitlements.index');
        Route::put('companies/{company}/entitlements', [OrganizationController::class, 'updateEntitlements'])->name('companies.entitlements.update');

        Route::get('permissions', [OrganizationController::class, 'permissions'])->name('permissions.index');
        Route::get('companies/{company}/roles', [OrganizationController::class, 'roles'])->name('companies.roles.index');
        Route::post('companies/{company}/roles', [OrganizationController::class, 'storeRole'])->name('companies.roles.store');
        Route::get('companies/{company}/roles/{role}', [OrganizationController::class, 'showRole'])->name('companies.roles.show');
        Route::put('companies/{company}/roles/{role}', [OrganizationController::class, 'updateRole'])->name('companies.roles.update');
        Route::delete('companies/{company}/roles/{role}', [OrganizationController::class, 'destroyRole'])->name('companies.roles.destroy');

        Route::get('companies/{company}/branches/{branch}/assignments', [OrganizationController::class, 'branchAssignments'])->name('companies.branches.assignments.index');
        Route::post('companies/{company}/branches/{branch}/assignments', [OrganizationController::class, 'storeBranchAssignment'])->name('companies.branches.assignments.store');
        Route::delete('companies/{company}/branches/{branch}/assignments/{user}', [OrganizationController::class, 'destroyBranchAssignment'])->name('companies.branches.assignments.destroy');
    });

Route::middleware(['auth:api', 'organization.company-context'])
    ->prefix('v1/platform')
    ->name('api.v1.platform.')
    ->group(function (): void {
        Route::get('currencies', [PlatformController::class, 'currencies'])->name('currencies.index');
        Route::get('settings', [PlatformController::class, 'settings'])->name('settings.index');
        Route::put('settings', [PlatformController::class, 'updateSettings'])->name('settings.update');
    });

Route::middleware(['auth:api', 'organization.company-context'])
    ->prefix('v1/partners')
    ->name('api.v1.partners.')
    ->group(function (): void {
        Route::get('/', [PartnersController::class, 'index'])->name('index');
        Route::post('/', [PartnersController::class, 'store'])->name('store');
        Route::get('{partner}', [PartnersController::class, 'show'])->name('show');
        Route::patch('{partner}', [PartnersController::class, 'update'])->name('update');
        Route::delete('{partner}', [PartnersController::class, 'destroy'])->name('destroy');

        Route::post('{partner}/contacts', [PartnersController::class, 'storeContact'])->name('contacts.store');
        Route::patch('{partner}/contacts/{contact}', [PartnersController::class, 'updateContact'])->name('contacts.update');
        Route::delete('{partner}/contacts/{contact}', [PartnersController::class, 'destroyContact'])->name('contacts.destroy');

        Route::post('{partner}/addresses', [PartnersController::class, 'storeAddress'])->name('addresses.store');
        Route::patch('{partner}/addresses/{address}', [PartnersController::class, 'updateAddress'])->name('addresses.update');
        Route::delete('{partner}/addresses/{address}', [PartnersController::class, 'destroyAddress'])->name('addresses.destroy');
    });

Route::middleware(['auth:api', 'organization.company-context'])
    ->prefix('v1/inventory')
    ->name('api.v1.inventory.')
    ->group(function (): void {
        Route::get('categories', [InventoryController::class, 'categories'])->name('categories.index');
        Route::post('categories', [InventoryController::class, 'storeCategory'])->name('categories.store');
        Route::get('categories/{category}', [InventoryController::class, 'showCategory'])->name('categories.show');
        Route::patch('categories/{category}', [InventoryController::class, 'updateCategory'])->name('categories.update');
        Route::post('categories/{category}/move', [InventoryController::class, 'moveCategory'])->name('categories.move');
        Route::delete('categories/{category}', [InventoryController::class, 'destroyCategory'])->name('categories.destroy');

        Route::get('brands', [InventoryController::class, 'brands'])->name('brands.index');
        Route::post('brands', [InventoryController::class, 'storeBrand'])->name('brands.store');
        Route::get('brands/{brand}', [InventoryController::class, 'showBrand'])->name('brands.show');
        Route::patch('brands/{brand}', [InventoryController::class, 'updateBrand'])->name('brands.update');
        Route::delete('brands/{brand}', [InventoryController::class, 'destroyBrand'])->name('brands.destroy');

        Route::get('units-of-measure', [InventoryController::class, 'units'])->name('units.index');
        Route::post('units-of-measure', [InventoryController::class, 'storeUnit'])->name('units.store');
        Route::get('units-of-measure/{unit}', [InventoryController::class, 'showUnit'])->name('units.show');
        Route::patch('units-of-measure/{unit}', [InventoryController::class, 'updateUnit'])->name('units.update');
        Route::delete('units-of-measure/{unit}', [InventoryController::class, 'destroyUnit'])->name('units.destroy');

        Route::get('products', [InventoryController::class, 'products'])->name('products.index');
        Route::post('products', [InventoryController::class, 'storeProduct'])->name('products.store');
        Route::get('products/{product}', [InventoryController::class, 'showProduct'])->name('products.show');
        Route::patch('products/{product}', [InventoryController::class, 'updateProduct'])->name('products.update');
        Route::delete('products/{product}', [InventoryController::class, 'destroyProduct'])->name('products.destroy');

        Route::post('products/{product}/variants', [InventoryController::class, 'storeVariant'])->name('products.variants.store');
        Route::patch('products/{product}/variants/{variant}', [InventoryController::class, 'updateVariant'])->name('products.variants.update');
        Route::delete('products/{product}/variants/{variant}', [InventoryController::class, 'destroyVariant'])->name('products.variants.destroy');

        Route::put('products/{product}/tags', [InventoryController::class, 'syncTags'])->name('products.tags.sync');

        Route::put('products/{product}/variants/{variant}/availability', [InventoryController::class, 'setAvailability'])->name('products.variants.availability.update');

        Route::post('stock/receipts', [InventoryController::class, 'storeReceipt'])->name('stock.receipts.store');
        Route::post('stock/issues', [InventoryController::class, 'storeIssue'])->name('stock.issues.store');
        Route::post('stock/adjustments', [InventoryController::class, 'storeAdjustment'])->name('stock.adjustments.store');
        Route::post('stock/transfers', [InventoryController::class, 'storeTransfer'])->name('stock.transfers.store');
        Route::get('stock/levels', [InventoryController::class, 'stockLevels'])->name('stock.levels.index');
        Route::get('stock/lots', [InventoryController::class, 'stockLots'])->name('stock.lots.index');
        Route::get('stock/movements', [InventoryController::class, 'stockMovements'])->name('stock.movements.index');
        Route::get('stock/valuation', [InventoryController::class, 'stockValuation'])->name('stock.valuation.index');

        Route::get('price-lists', [InventoryController::class, 'priceLists'])->name('price-lists.index');
        Route::post('price-lists', [InventoryController::class, 'storePriceList'])->name('price-lists.store');
        Route::put('price-lists/{priceList}/prices', [InventoryController::class, 'setPrice'])->name('price-lists.prices.set');
        Route::get('products/{product}/variants/{variant}/price', [InventoryController::class, 'resolvePrice'])->name('products.variants.price.resolve');

        Route::get('discounts', [InventoryController::class, 'discounts'])->name('discounts.index');
        Route::get('discounts/{discount}', [InventoryController::class, 'showDiscount'])->name('discounts.show');
        Route::post('discounts', [InventoryController::class, 'storeDiscount'])->name('discounts.store');
        Route::delete('discounts/{discount}', [InventoryController::class, 'destroyDiscount'])->name('discounts.destroy');

        Route::get('rewards', [InventoryController::class, 'rewards'])->name('rewards.index');
        Route::post('rewards', [InventoryController::class, 'storeReward'])->name('rewards.store');
        Route::delete('rewards/{reward}', [InventoryController::class, 'destroyReward'])->name('rewards.destroy');
    });
