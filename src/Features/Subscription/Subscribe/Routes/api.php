<?php

use Features\Subscription\Subscribe\Application\PreviewPlanChangeController;
use Features\Subscription\Subscribe\Application\SubscribeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'tenant.required', 'tenant.role:admin', 'rate.limit'])
    ->post('api/v1/subscriptions', SubscribeController::class)
    ->name('api.v1.subscriptions.subscribe');

Route::middleware(['auth:sanctum', 'tenant.context', 'tenant.required', 'tenant.role:admin', 'rate.limit'])
    ->post('api/v1/subscriptions/preview', PreviewPlanChangeController::class)
    ->name('api.v1.subscriptions.preview');
