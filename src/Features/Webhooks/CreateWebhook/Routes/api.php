<?php

use Features\Webhooks\CreateWebhook\Application\CreateWebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'tenant.required', 'tenant.role:admin', 'rate.limit'])
    ->post('api/v1/webhooks', CreateWebhookController::class)
    ->name('api.v1.webhooks.store');
