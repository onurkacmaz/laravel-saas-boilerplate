<?php

use Features\Subscription\Subscribe\Application\SubscribeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'rate.limit', 'tenant.required'])
    ->post('api/v1/subscriptions', SubscribeController::class)
    ->name('api.v1.subscriptions.subscribe');
