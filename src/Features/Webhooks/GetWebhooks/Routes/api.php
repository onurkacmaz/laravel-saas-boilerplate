<?php

use Features\Webhooks\GetWebhooks\Application\GetWebhooksController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'tenant.required', 'tenant.role:admin', 'rate.limit'])
    ->get('api/v1/webhooks', GetWebhooksController::class)
    ->name('api.v1.webhooks.index');
