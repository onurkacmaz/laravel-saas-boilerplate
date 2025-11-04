<?php

namespace Features\Acl\GetPermissions\Providers;

use Illuminate\Support\ServiceProvider;

class GetPermissionsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
