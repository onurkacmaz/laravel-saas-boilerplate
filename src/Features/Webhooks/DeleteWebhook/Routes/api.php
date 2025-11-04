<?php

use Features\Webhooks\DeleteWebhook\Application\DeleteWebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'rate.limit', 'tenant.required'])
    ->delete('/api/v1/webhooks/{webhook}', DeleteWebhookController::class)
    ->name('api.v1.webhooks.destroy');
