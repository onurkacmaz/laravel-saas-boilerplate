<?php

namespace Features\Acl\AssignToUser\Providers;

use Illuminate\Support\ServiceProvider;

class AssignToUserServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
