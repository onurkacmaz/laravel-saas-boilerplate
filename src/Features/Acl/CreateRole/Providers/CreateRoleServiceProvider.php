<?php

namespace Features\Acl\CreateRole\Providers;

use Illuminate\Support\ServiceProvider;

class CreateRoleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
