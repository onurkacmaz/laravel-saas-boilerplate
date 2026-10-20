<?php

use Features\Auth\EmailVerification\Application\ResendVerificationEmailController;
use Features\Auth\EmailVerification\Application\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/auth/email')
    ->name('api.v1.auth.verification.')
    ->group(function () {
        Route::get('/verify/{id}/{hash}', VerifyEmailController::class)
            ->middleware(['signed', 'throttle:6,1'])
            ->whereUuid('id')
            ->name('verify');

        Route::post('/verification-notification', ResendVerificationEmailController::class)
            ->middleware(['auth:sanctum', 'throttle:verification'])
            ->name('send');
    });
