<?php

use Features\Subscription\GetUsage\Application\GetUsageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['rate.limit', 'auth:sanctum', 'tenant.context', 'tenant.required'])
    ->get('api/v1/usage/', GetUsageController::class)
    ->name('api.v1.usage.index');
