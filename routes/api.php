<?php

use App\Modules\Authentication\Http\Controllers\AuthenticationController;
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

        Route::middleware('auth:api')->group(function (): void {
            Route::get('me', [AuthenticationController::class, 'me'])->name('me');
            Route::post('logout', [AuthenticationController::class, 'logout'])->name('logout');
        });
    });
