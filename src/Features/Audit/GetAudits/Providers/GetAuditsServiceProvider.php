<?php

namespace Features\Audit\GetAudits\Providers;

use Illuminate\Support\ServiceProvider;

class GetAuditsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
