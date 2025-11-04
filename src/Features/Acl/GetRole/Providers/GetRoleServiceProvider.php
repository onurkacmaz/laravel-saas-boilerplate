<?php

namespace Features\Acl\GetRole\Providers;

use Illuminate\Support\ServiceProvider;

class GetRoleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
