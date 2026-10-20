<?php

use Features\Auth\ChangePassword\Application\ChangePasswordController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:6,1'])
    ->put('api/v1/auth/password', ChangePasswordController::class)
    ->name('api.v1.auth.password.update');
