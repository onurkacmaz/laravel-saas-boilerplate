<?php

use Features\Webhooks\GetWebhook\Application\GetWebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'tenant.required', 'tenant.role:admin', 'rate.limit'])
    ->get('api/v1/webhooks/{webhook}', GetWebhookController::class)
    ->name('api.v1.webhooks.show');
