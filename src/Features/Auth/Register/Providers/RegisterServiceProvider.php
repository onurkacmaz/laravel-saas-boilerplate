<?php

namespace Features\Auth\Register\Providers;

use Features\Auth\Register\Events\UserRegisterFailed;
use Features\Auth\Register\Events\UserRegisterSucceeded;
use Features\Auth\Register\Listeners\NotifyFailedRegistration;
use Features\Auth\Register\Listeners\SendEmailVerificationNotification;
use Features\Auth\Register\Listeners\SendWelcomeNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Infrastructure\Support\LoadsApiRoutes;

class RegisterServiceProvider extends ServiceProvider
{
    use LoadsApiRoutes;

    public function boot(): void
    {
        $this->loadApiRoutesFrom(__DIR__.'/../Routes/api.php');

        Event::listen(UserRegisterSucceeded::class, SendWelcomeNotification::class);
        Event::listen(UserRegisterSucceeded::class, SendEmailVerificationNotification::class);
        Event::listen(UserRegisterFailed::class, NotifyFailedRegistration::class);
    }
}
