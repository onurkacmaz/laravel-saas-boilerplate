<?php

use Features\Auth\Login\Application\LoginController;
use Features\Auth\Login\Application\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->middleware('api')
    ->name('api.v1.')
    ->group(function () {
        Route::prefix('auth')
            ->name('auth.')
            ->group(function () {
                Route::post('/login', [LoginController::class, '__invoke'])
                    ->middleware('throttle:auth')
                    ->name('login');
            });

        Route::prefix('2fa')
            ->name('2fa.')
            ->middleware(['guest', 'throttle:two-factor'])
            ->group(function () {
                Route::post('/challenge', [TwoFactorAuthenticationController::class, '__invoke'])
                    ->name('challenge');
            });
    });
