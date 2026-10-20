<?php

use Features\Subscription\Subscribe\Application\SubscribeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'tenant.required', 'tenant.role:admin', 'rate.limit'])
    ->post('api/v1/subscriptions', SubscribeController::class)
    ->name('api.v1.subscriptions.subscribe');
