<?php

use Features\Subscription\PolarWebhook\Application\PolarWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('polar/webhook', PolarWebhookController::class)
    ->name('webhooks.polar');
