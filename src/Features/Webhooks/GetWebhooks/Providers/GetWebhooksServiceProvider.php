<?php

namespace Features\Webhooks\GetWebhooks\Providers;

use Illuminate\Support\ServiceProvider;

class GetWebhooksServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
