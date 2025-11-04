<?php

namespace Features\Webhooks\GetWebhookDeliveries\Providers;

use Illuminate\Support\ServiceProvider;

class GetWebhookDeliveriesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
