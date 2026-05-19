<?php

use App\Modules\Authentication\Http\Controllers\AuthenticationController;
use App\Modules\Identity\Http\Controllers\IdentityController;
use App\Modules\Organization\Http\Controllers\ModuleRegistryController;
use App\Modules\Organization\Http\Controllers\OrganizationController;
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
    });
