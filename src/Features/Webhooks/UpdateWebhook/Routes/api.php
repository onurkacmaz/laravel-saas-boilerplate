<?php

use Features\Webhooks\UpdateWebhook\Application\UpdateWebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'rate.limit', 'tenant.required'])
    ->put('api/v1/webhooks/{webhook}', UpdateWebhookController::class)
    ->name('api.v1.webhooks.update');
