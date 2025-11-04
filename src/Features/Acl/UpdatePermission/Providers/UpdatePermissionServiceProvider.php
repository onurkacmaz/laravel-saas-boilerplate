<?php

namespace Features\Acl\UpdatePermission\Providers;

use Illuminate\Support\ServiceProvider;

class UpdatePermissionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
