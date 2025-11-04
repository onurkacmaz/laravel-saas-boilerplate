<?php

use Features\Subscription\GetSubscription\Application\GetSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'rate.limit', 'tenant.required'])
    ->get('api/v1/subscriptions', GetSubscriptionController::class)
    ->name('api.v1.subscriptions.show');
