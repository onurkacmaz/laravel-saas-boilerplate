<?php

use Features\Auth\PasswordReset\Application\ForgotPasswordController;
use Features\Auth\PasswordReset\Application\ResetPasswordController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/auth')
    ->name('api.v1.auth.')
    ->middleware('throttle:auth')
    ->group(function () {
        Route::post('/forgot-password', ForgotPasswordController::class)->name('password.email');
        Route::post('/reset-password', ResetPasswordController::class)->name('password.reset');
    });
