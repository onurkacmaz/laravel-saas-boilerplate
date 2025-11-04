<?php

namespace Features\Acl\AttachPermission\Providers;

use Illuminate\Support\ServiceProvider;

class AttachPermissionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
