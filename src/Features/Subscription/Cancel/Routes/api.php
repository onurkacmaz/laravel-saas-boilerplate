<?php

use Features\Subscription\Cancel\Application\CancelController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'tenant.required', 'tenant.role:admin', 'rate.limit'])
    ->delete('api/v1/subscriptions', CancelController::class)
    ->name('api.v1.subscriptions.cancel');
