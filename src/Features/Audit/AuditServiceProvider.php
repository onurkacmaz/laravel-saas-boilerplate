<?php

namespace Features\Audit;

use Features\Audit\GetAudit\Providers\GetAuditServiceProvider;
use Features\Audit\GetAudits\Providers\GetAuditsServiceProvider;
use Features\Audit\GetEvents\Providers\GetEventsServiceProvider;
use Illuminate\Support\ServiceProvider;

class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadMigrationsFrom([
            __DIR__.'/../../Features/Audit/Infrastructure/Eloquent/Migrations',
        ]);

        $this->app->register(GetAuditServiceProvider::class);
        $this->app->register(GetAuditsServiceProvider::class);
        $this->app->register(GetEventsServiceProvider::class);
    }
}
