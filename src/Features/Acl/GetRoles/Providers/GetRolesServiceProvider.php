<?php

namespace Features\Acl\GetRoles\Providers;

use Illuminate\Support\ServiceProvider;

class GetRolesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
