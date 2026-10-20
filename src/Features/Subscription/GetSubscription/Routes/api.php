<?php

use Features\Subscription\GetSubscription\Application\GetSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'tenant.required', 'rate.limit'])
    ->get('api/v1/subscriptions', GetSubscriptionController::class)
    ->name('api.v1.subscriptions.show');
