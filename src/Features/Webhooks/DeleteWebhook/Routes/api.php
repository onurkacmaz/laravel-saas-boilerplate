<?php

use Features\Webhooks\DeleteWebhook\Application\DeleteWebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'tenant.required', 'tenant.role:admin', 'rate.limit'])
    ->delete('/api/v1/webhooks/{webhook}', DeleteWebhookController::class)
    ->name('api.v1.webhooks.destroy');
