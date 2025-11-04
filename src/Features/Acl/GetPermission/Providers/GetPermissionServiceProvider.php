<?php

namespace Features\Acl\GetPermission\Providers;

use Illuminate\Support\ServiceProvider;

class GetPermissionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
