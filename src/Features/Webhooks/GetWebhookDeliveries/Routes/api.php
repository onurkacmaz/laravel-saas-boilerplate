<?php

use Features\Webhooks\GetWebhookDeliveries\Application\GetWebhookDeliveriesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'tenant.required', 'tenant.role:admin', 'rate.limit'])
    ->get('api/v1/webhooks/{webhook}/deliveries', GetWebhookDeliveriesController::class)
    ->name('api.v1.webhooks.deliveries');
