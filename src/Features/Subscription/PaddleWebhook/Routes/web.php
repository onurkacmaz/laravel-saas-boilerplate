<?php

use Features\Subscription\PaddleWebhook\Application\PaddleCheckoutController;
use Illuminate\Support\Facades\Route;

Route::get('paddle/checkout', PaddleCheckoutController::class)
    ->name('paddle.checkout');
