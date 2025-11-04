<?php

namespace Features\Acl\RemoveFromUser\Providers;

use Illuminate\Support\ServiceProvider;

class RemoveFromUserServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
