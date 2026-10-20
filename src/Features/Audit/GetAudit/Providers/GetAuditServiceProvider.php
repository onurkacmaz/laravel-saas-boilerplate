<?php

namespace Features\Audit\GetAudit\Providers;

use Illuminate\Support\ServiceProvider;
use Infrastructure\Support\LoadsApiRoutes;

class GetAuditServiceProvider extends ServiceProvider
{
    use LoadsApiRoutes;

    public function boot(): void
    {
        $this->loadApiRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
