<?php

use Features\Subscription\PaddleWebhook\Application\PaddleWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('paddle/webhook', PaddleWebhookController::class)
    ->name('webhooks.paddle');
