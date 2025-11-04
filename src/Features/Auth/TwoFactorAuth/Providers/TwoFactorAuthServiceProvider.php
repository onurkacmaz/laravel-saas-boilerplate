<?php

namespace Features\Auth\TwoFactorAuth\Providers;

use Features\Auth\TwoFactorAuth\Events\TwoFactorDisabled;
use Features\Auth\TwoFactorAuth\Events\TwoFactorEnabled;
use Features\Auth\TwoFactorAuth\Listeners\SendTwoFactorDisabledNotification;
use Features\Auth\TwoFactorAuth\Listeners\SendTwoFactorEnabledNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class TwoFactorAuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');

        Event::listen(TwoFactorDisabled::class, SendTwoFactorDisabledNotification::class);
        Event::listen(TwoFactorEnabled::class, SendTwoFactorEnabledNotification::class);
    }
}
