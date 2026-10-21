<?php

use Features\Webhooks\GetWebhookEvents\Application\GetWebhookEventsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'tenant.required', 'tenant.role:admin', 'rate.limit'])
    ->get('api/v1/webhooks/events', GetWebhookEventsController::class)
    ->name('api.v1.webhooks.events');
