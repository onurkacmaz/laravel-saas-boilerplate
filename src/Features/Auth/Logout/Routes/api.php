<?php

use Features\Auth\Logout\Application\LogoutController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])
    ->post('api/v1/auth/logout', LogoutController::class)
    ->name('api.v1.auth.logout');
