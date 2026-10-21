<?php

use Features\Auth\UpdateProfile\Application\UpdateProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:6,1'])
    ->put('api/v1/auth/profile', UpdateProfileController::class)
    ->name('api.v1.auth.profile.update');
