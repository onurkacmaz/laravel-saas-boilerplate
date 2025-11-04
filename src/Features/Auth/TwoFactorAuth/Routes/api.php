<?php

use Features\Auth\TwoFactorAuth\Application\TwoFactorController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->middleware('api')
    ->name('api.v1.')
    ->group(function () {
        Route::prefix('2fa')
            ->name('2fa.')
            ->group(function () {
                Route::middleware(['auth:sanctum', 'tenant.context'])
                    ->group(function () {
                        Route::post('/enable', [TwoFactorController::class, 'enable'])
                            ->name('enable');
                        Route::post('/confirm', [TwoFactorController::class, 'confirm'])
                            ->name('confirm');
                        Route::post('/disable', [TwoFactorController::class, 'disable'])
                            ->name('disable');
                        Route::get('/recovery-codes', [TwoFactorController::class, 'recoveryCodes'])
                            ->name('recovery-codes');
                    });
            });
    });
