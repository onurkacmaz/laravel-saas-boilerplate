<?php

namespace Features\Audit\GetEvents\Providers;

use Illuminate\Support\ServiceProvider;

class GetEventsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
