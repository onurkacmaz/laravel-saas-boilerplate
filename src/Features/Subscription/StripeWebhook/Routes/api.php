<?php

use Features\Subscription\StripeWebhook\Application\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('stripe/webhook', [StripeWebhookController::class, 'handleWebhook'])
    ->name('webhooks.stripe');
