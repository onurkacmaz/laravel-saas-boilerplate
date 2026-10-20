<?php

namespace Features\Acl\RemoveFromUser\Providers;

use Illuminate\Support\ServiceProvider;
use Infrastructure\Support\LoadsApiRoutes;

class RemoveFromUserServiceProvider extends ServiceProvider
{
    use LoadsApiRoutes;

    public function boot(): void
    {
        $this->loadApiRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
