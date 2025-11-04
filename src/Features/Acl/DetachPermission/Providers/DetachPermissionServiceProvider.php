<?php

namespace Features\Acl\DetachPermission\Providers;

use Illuminate\Support\ServiceProvider;

class DetachPermissionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
