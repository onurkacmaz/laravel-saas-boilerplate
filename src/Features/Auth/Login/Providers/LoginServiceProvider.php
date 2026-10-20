<?php

namespace Features\Auth\Login\Providers;

use Features\Auth\Login\Events\LoginFailed;
use Features\Auth\Login\Events\LoginSucceeded;
use Features\Auth\Login\Listeners\NotifyFailedLogin;
use Features\Auth\Login\Listeners\UpdateLastLoginAt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Infrastructure\Support\LoadsApiRoutes;

class LoginServiceProvider extends ServiceProvider
{
    use LoadsApiRoutes;

    public function boot(): void
    {
        $this->loadApiRoutesFrom(__DIR__.'/../Routes/api.php');

        Event::listen(LoginFailed::class, NotifyFailedLogin::class);
        Event::listen(LoginSucceeded::class, UpdateLastLoginAt::class);
    }
}
