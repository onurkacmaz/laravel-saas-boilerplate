<?php

use Features\Webhooks\UpdateWebhook\Application\UpdateWebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'tenant.required', 'tenant.role:admin', 'rate.limit'])
    ->put('api/v1/webhooks/{webhook}', UpdateWebhookController::class)
    ->name('api.v1.webhooks.update');
