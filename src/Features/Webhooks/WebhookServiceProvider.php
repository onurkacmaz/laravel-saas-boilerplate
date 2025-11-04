<?php

namespace Features\Webhooks;

use Illuminate\Support\ServiceProvider;

class WebhookServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadMigrationsFrom([
            __DIR__.'/../../Features/Webhooks/Infrastructure/Eloquent/Migrations',
        ]);
    }
}
