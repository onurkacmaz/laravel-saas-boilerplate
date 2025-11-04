<?php

namespace Features\Acl\UpdateRole\Providers;

use Illuminate\Support\ServiceProvider;

class UpdateRoleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
