<?php

namespace Features\Webhooks\DeleteWebhook\Providers;

use Illuminate\Support\ServiceProvider;

class DeleteWebhookServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
