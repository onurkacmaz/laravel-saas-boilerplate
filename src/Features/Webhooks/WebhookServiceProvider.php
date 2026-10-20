<?php

namespace Features\Webhooks;

use Features\Webhooks\Shared\Listeners\DispatchDomainEventWebhooks;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class WebhookServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadMigrationsFrom([
            __DIR__.'/../../Features/Webhooks/Infrastructure/Eloquent/Migrations',
        ]);
    }

    public function boot(): void
    {
        Event::subscribe(DispatchDomainEventWebhooks::class);
    }
}
