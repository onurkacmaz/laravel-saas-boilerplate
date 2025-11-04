<?php

namespace Features\Webhooks\UpdateWebhook\Providers;

use Illuminate\Support\ServiceProvider;

class UpdateWebhookServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
