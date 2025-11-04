<?php

namespace Features\Subscription\StripeWebhook\Providers;

use Illuminate\Support\ServiceProvider;

class StripeWebhookServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
