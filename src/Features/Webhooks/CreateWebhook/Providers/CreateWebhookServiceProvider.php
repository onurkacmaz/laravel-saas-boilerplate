<?php

namespace Features\Webhooks\CreateWebhook\Providers;

use Illuminate\Support\ServiceProvider;

class CreateWebhookServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
