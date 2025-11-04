<?php

namespace Features\Audit\GetAudit\Providers;

use Illuminate\Support\ServiceProvider;

class GetAuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
