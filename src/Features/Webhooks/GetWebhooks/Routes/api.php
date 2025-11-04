<?php

use Features\Webhooks\GetWebhooks\Application\GetWebhooksController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'rate.limit', 'tenant.required'])
    ->get('api/v1/webhooks', GetWebhooksController::class)
    ->name('api.v1.webhooks.index');
