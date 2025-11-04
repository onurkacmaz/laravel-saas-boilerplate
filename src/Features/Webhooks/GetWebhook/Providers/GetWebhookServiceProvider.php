<?php

namespace Features\Webhooks\GetWebhook\Providers;

use Illuminate\Support\ServiceProvider;

class GetWebhookServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
