<?php

namespace Features\Acl\CreatePermission\Providers;

use Illuminate\Support\ServiceProvider;

class CreatePermissionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
