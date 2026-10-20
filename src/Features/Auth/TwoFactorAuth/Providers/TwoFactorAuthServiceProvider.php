<?php

namespace Features\Auth\TwoFactorAuth\Providers;

use Features\Auth\TwoFactorAuth\Events\TwoFactorDisabled;
use Features\Auth\TwoFactorAuth\Events\TwoFactorEnabled;
use Features\Auth\TwoFactorAuth\Listeners\SendTwoFactorDisabledNotification;
use Features\Auth\TwoFactorAuth\Listeners\SendTwoFactorEnabledNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Infrastructure\Support\LoadsApiRoutes;

class TwoFactorAuthServiceProvider extends ServiceProvider
{
    use LoadsApiRoutes;

    public function boot(): void
    {
        $this->loadApiRoutesFrom(__DIR__.'/../Routes/api.php');

        Event::listen(TwoFactorDisabled::class, SendTwoFactorDisabledNotification::class);
        Event::listen(TwoFactorEnabled::class, SendTwoFactorEnabledNotification::class);
    }
}
