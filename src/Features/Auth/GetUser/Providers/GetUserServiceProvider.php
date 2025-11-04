<?php

namespace Features\Auth\GetUser\Providers;

use Illuminate\Support\ServiceProvider;

class GetUserServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
