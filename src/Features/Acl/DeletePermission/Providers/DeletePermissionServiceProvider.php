<?php

namespace Features\Acl\DeletePermission\Providers;

use Illuminate\Support\ServiceProvider;

class DeletePermissionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
