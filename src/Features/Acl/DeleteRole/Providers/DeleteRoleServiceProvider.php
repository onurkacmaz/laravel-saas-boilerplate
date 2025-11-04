<?php

namespace Features\Acl\DeleteRole\Providers;

use Illuminate\Support\ServiceProvider;

class DeleteRoleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
