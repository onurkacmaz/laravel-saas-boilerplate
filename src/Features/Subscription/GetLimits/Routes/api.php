<?php

use Features\Subscription\GetLimits\Application\GetLimitsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['rate.limit', 'auth:sanctum', 'tenant.context', 'tenant.required'])
    ->get('api/v1/usage/limits', GetLimitsController::class)
    ->name('api.v1.usage.limits');
