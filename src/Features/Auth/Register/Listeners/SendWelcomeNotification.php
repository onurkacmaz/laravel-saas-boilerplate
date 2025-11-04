<?php

namespace Features\Auth\Register\Listeners;

use Features\Auth\Register\Events\UserRegisterSucceeded;
use Features\Auth\Register\Notifications\WelcomeNotification;

class SendWelcomeNotification
{
    /**
     * Handle the event.
     */
    public function handle(UserRegisterSucceeded $event): void
    {
        $user = $event->user;
        $user->notify(new WelcomeNotification($user));
    }
}
