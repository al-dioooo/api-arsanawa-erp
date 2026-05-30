<?php

use App\Modules\Authentication\Http\Controllers\AuthenticationController;
use App\Modules\Finance\Http\Controllers\FinanceController;
use App\Modules\Identity\Http\Controllers\IdentityController;
use App\Modules\Inventory\Http\Controllers\ExternalProductController;
use App\Modules\Inventory\Http\Controllers\InventoryController;
use App\Modules\Organization\Http\Controllers\ModuleRegistryController;
use App\Modules\Organization\Http\Controllers\OrganizationController;
use App\Modules\Partners\Http\Controllers\PartnersController;
use App\Modules\Platform\Http\Controllers\PlatformController;
use App\Modules\Pos\Http\Controllers\ExternalCateringOrderController;
use App\Modules\Pos\Http\Controllers\PosController;
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
        Route::get('companies/{company}/api-keys', [OrganizationController::class, 'apiKeys'])->name('companies.api-keys.index');
        Route::post('companies/{company}/api-keys', [OrganizationController::class, 'storeApiKey'])->name('companies.api-keys.store');
        Route::get('companies/{company}/api-keys/{apiKey}', [OrganizationController::class, 'showApiKey'])->name('companies.api-keys.show');
        Route::post('companies/{company}/api-keys/{apiKey}/rotate', [OrganizationController::class, 'rotateApiKey'])->name('companies.api-keys.rotate');
        Route::post('companies/{company}/api-keys/{apiKey}/revoke', [OrganizationController::class, 'revokeApiKey'])->name('companies.api-keys.revoke');

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

Route::middleware(['external.api-key', 'throttle:external-api'])
    ->prefix('v1/external')
    ->name('api.v1.external.')
    ->group(function (): void {
        Route::get('products', [ExternalProductController::class, 'index'])->name('products.index');
        Route::get('products/{variant}/price', [ExternalProductController::class, 'price'])->name('products.price');
        Route::post('catering-orders', [ExternalCateringOrderController::class, 'store'])->name('catering-orders.store');
        Route::get('catering-orders/{externalReference}', [ExternalCateringOrderController::class, 'show'])->name('catering-orders.show');
        Route::patch('catering-orders/{externalReference}/status', [ExternalCateringOrderController::class, 'updateStatus'])->name('catering-orders.status.update');
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

Route::middleware(['auth:api', 'organization.company-context'])
    ->prefix('v1/finance')
    ->name('api.v1.finance.')
    ->group(function (): void {
        // Accounts
        Route::get('accounts', [FinanceController::class, 'accounts'])->name('accounts.index');
        Route::post('accounts', [FinanceController::class, 'storeAccount'])->name('accounts.store');
        Route::get('accounts/{account}', [FinanceController::class, 'showAccount'])->name('accounts.show');
        Route::patch('accounts/{account}', [FinanceController::class, 'updateAccount'])->name('accounts.update');
        Route::delete('accounts/{account}', [FinanceController::class, 'destroyAccount'])->name('accounts.destroy');

        // Periods
        Route::get('periods', [FinanceController::class, 'periods'])->name('periods.index');
        Route::post('periods', [FinanceController::class, 'storePeriod'])->name('periods.store');
        Route::post('periods/{period}/close', [FinanceController::class, 'closePeriod'])->name('periods.close');
        Route::post('periods/{period}/reopen', [FinanceController::class, 'reopenPeriod'])->name('periods.reopen');

        // Tax Rates
        Route::get('tax-rates', [FinanceController::class, 'taxRates'])->name('tax-rates.index');
        Route::post('tax-rates', [FinanceController::class, 'storeTaxRate'])->name('tax-rates.store');
        Route::get('tax-rates/{taxRate}', [FinanceController::class, 'showTaxRate'])->name('tax-rates.show');
        Route::patch('tax-rates/{taxRate}', [FinanceController::class, 'updateTaxRate'])->name('tax-rates.update');
        Route::delete('tax-rates/{taxRate}', [FinanceController::class, 'destroyTaxRate'])->name('tax-rates.destroy');

        // Account Mappings
        Route::get('account-mappings', [FinanceController::class, 'accountMappings'])->name('account-mappings.index');
        Route::put('account-mappings', [FinanceController::class, 'upsertAccountMappings'])->name('account-mappings.upsert');

        // Journal Entries
        Route::get('journal-entries', [FinanceController::class, 'journalEntries'])->name('journal-entries.index');
        Route::post('journal-entries', [FinanceController::class, 'storeJournalEntry'])->name('journal-entries.store');
        Route::get('journal-entries/{journalEntry}', [FinanceController::class, 'showJournalEntry'])->name('journal-entries.show');
        Route::post('journal-entries/{journalEntry}/post', [FinanceController::class, 'postJournalEntry'])->name('journal-entries.post');
        Route::post('journal-entries/{journalEntry}/void', [FinanceController::class, 'voidJournalEntry'])->name('journal-entries.void');

        // Invoices
        Route::get('invoices', [FinanceController::class, 'invoices'])->name('invoices.index');
        Route::post('invoices', [FinanceController::class, 'storeInvoice'])->name('invoices.store');
        Route::get('invoices/{invoice}', [FinanceController::class, 'showInvoice'])->name('invoices.show');
        Route::patch('invoices/{invoice}', [FinanceController::class, 'updateInvoice'])->name('invoices.update');
        Route::post('invoices/{invoice}/post', [FinanceController::class, 'postInvoice'])->name('invoices.post');
        Route::post('invoices/{invoice}/void', [FinanceController::class, 'voidInvoice'])->name('invoices.void');

        // Bills
        Route::get('bills', [FinanceController::class, 'bills'])->name('bills.index');
        Route::post('bills', [FinanceController::class, 'storeBill'])->name('bills.store');
        Route::get('bills/{bill}', [FinanceController::class, 'showBill'])->name('bills.show');
        Route::patch('bills/{bill}', [FinanceController::class, 'updateBill'])->name('bills.update');
        Route::post('bills/{bill}/post', [FinanceController::class, 'postBill'])->name('bills.post');
        Route::post('bills/{bill}/void', [FinanceController::class, 'voidBill'])->name('bills.void');

        // Payments
        Route::get('payments', [FinanceController::class, 'payments'])->name('payments.index');
        Route::post('payments', [FinanceController::class, 'storePayment'])->name('payments.store');
        Route::get('payments/{payment}', [FinanceController::class, 'showPayment'])->name('payments.show');
        Route::patch('payments/{payment}', [FinanceController::class, 'updatePayment'])->name('payments.update');
        Route::post('payments/{payment}/post', [FinanceController::class, 'postPayment'])->name('payments.post');
        Route::post('payments/{payment}/void', [FinanceController::class, 'voidPayment'])->name('payments.void');

        // Reports
        Route::get('reports/trial-balance', [FinanceController::class, 'trialBalance'])->name('reports.trial-balance');
        Route::get('reports/account-ledger', [FinanceController::class, 'accountLedger'])->name('reports.account-ledger');

        // Approval Matrices
        Route::get('approval-matrices', [FinanceController::class, 'approvalMatrices'])->name('approval-matrices.index');
        Route::post('approval-matrices', [FinanceController::class, 'storeApprovalMatrix'])->name('approval-matrices.store');
        Route::patch('approval-matrices/{approvalMatrix}', [FinanceController::class, 'updateApprovalMatrix'])->name('approval-matrices.update');
        Route::delete('approval-matrices/{approvalMatrix}', [FinanceController::class, 'destroyApprovalMatrix'])->name('approval-matrices.destroy');

        // Approval Requests & Workflow
        Route::get('approval-requests', [FinanceController::class, 'approvalRequests'])->name('approval-requests.index');
        Route::post('approval-requests/{approvalRequest}/act', [FinanceController::class, 'actOnApproval'])->name('approval-requests.act');
        Route::post('bills/{bill}/submit-approval', [FinanceController::class, 'submitBillApproval'])->name('bills.submit-approval');
        Route::post('payments/{payment}/submit-approval', [FinanceController::class, 'submitPaymentApproval'])->name('payments.submit-approval');

        // Tax Returns
        Route::get('tax-returns', [FinanceController::class, 'taxReturns'])->name('tax-returns.index');
        Route::post('tax-returns', [FinanceController::class, 'storeTaxReturn'])->name('tax-returns.store');
        Route::get('tax-returns/{taxReturn}', [FinanceController::class, 'showTaxReturn'])->name('tax-returns.show');
        Route::post('tax-returns/{taxReturn}/finalize', [FinanceController::class, 'finalizeTaxReturn'])->name('tax-returns.finalize');
        Route::delete('tax-returns/{taxReturn}', [FinanceController::class, 'destroyTaxReturn'])->name('tax-returns.destroy');
    });

Route::middleware(['auth:api', 'organization.company-context'])
    ->prefix('v1/pos')
    ->name('api.v1.pos.')
    ->group(function (): void {
        Route::get('sales', [PosController::class, 'sales'])->name('sales.index');
        Route::post('sales', [PosController::class, 'storeSale'])->name('sales.store');
        Route::get('sales/{sale}', [PosController::class, 'showSale'])->name('sales.show');
        Route::patch('sales/{sale}', [PosController::class, 'updateSale'])->name('sales.update');
        Route::post('sales/{sale}/apply-promotions', [PosController::class, 'applyPromotions'])->name('sales.apply-promotions');
        Route::post('sales/{sale}/confirm', [PosController::class, 'confirmOrder'])->name('sales.confirm');
        Route::post('sales/{sale}/complete', [PosController::class, 'completeSale'])->name('sales.complete');
        Route::post('sales/{sale}/cancel', [PosController::class, 'cancelSale'])->name('sales.cancel');
        Route::post('sales/{sale}/void', [PosController::class, 'voidSale'])->name('sales.void');
        Route::post('sales/{sale}/payments', [PosController::class, 'addSalePayment'])->name('sales.payments.store');
        Route::delete('sales/{sale}/payments/{payment}', [PosController::class, 'removeSalePayment'])->name('sales.payments.destroy');

        Route::get('reports/sales', [PosController::class, 'salesReport'])->name('reports.sales');
        Route::get('reports/shifts/{shift}', [PosController::class, 'shiftReport'])->name('reports.shifts.show');

        Route::get('registers', [PosController::class, 'registers'])->name('registers.index');
        Route::post('registers', [PosController::class, 'storeRegister'])->name('registers.store');
        Route::get('registers/{register}', [PosController::class, 'showRegister'])->name('registers.show');
        Route::patch('registers/{register}', [PosController::class, 'updateRegister'])->name('registers.update');
        Route::delete('registers/{register}', [PosController::class, 'destroyRegister'])->name('registers.destroy');

        Route::get('shifts', [PosController::class, 'shifts'])->name('shifts.index');
        Route::get('shifts/current', [PosController::class, 'currentShift'])->name('shifts.current');
        Route::post('shifts/open', [PosController::class, 'openShift'])->name('shifts.open');
        Route::post('shifts/{shift}/close', [PosController::class, 'closeShift'])->name('shifts.close');
    });
