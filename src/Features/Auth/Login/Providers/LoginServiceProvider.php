<?php

namespace Features\Auth\Login\Providers;

use Features\Auth\Login\Events\LoginFailed;
use Features\Auth\Login\Events\LoginSucceeded;
use Features\Auth\Login\Listeners\NotifyFailedLogin;
use Features\Auth\Login\Listeners\UpdateLastLoginAt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class LoginServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');

        Event::listen(LoginFailed::class, NotifyFailedLogin::class);
        Event::listen(LoginSucceeded::class, UpdateLastLoginAt::class);
    }
}
