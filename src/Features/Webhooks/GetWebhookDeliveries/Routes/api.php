<?php

use Features\Webhooks\GetWebhookDeliveries\Application\GetWebhookDeliveriesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'rate.limit', 'tenant.required'])
    ->get('api/v1/webhooks/{webhook}/deliveries', GetWebhookDeliveriesController::class)
    ->name('api.v1.webhooks.deliveries');
