<?php

use Features\Auth\Register\Application\RegisterController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->middleware('api')
    ->name('api.v1.')
    ->group(function () {
        Route::prefix('auth')
            ->name('auth.')
            ->group(function () {
                Route::post('/register', [RegisterController::class, '__invoke'])
                    ->middleware('throttle:auth')
                    ->name('register');
            });
    });
