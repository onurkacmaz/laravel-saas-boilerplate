<?php

namespace Features\Auth\Register\Listeners;

use Features\Auth\Register\Events\UserRegisterSucceeded;
use Illuminate\Contracts\Auth\MustVerifyEmail;

class SendEmailVerificationNotification
{
    /**
     * Send the e-mail verification link to newly registered users.
     *
     * Laravel's built-in listener is bound to the base Registered event only,
     * and listeners are not inherited by event subclasses.
     */
    public function handle(UserRegisterSucceeded $event): void
    {
        if ($event->user instanceof MustVerifyEmail && ! $event->user->hasVerifiedEmail()) {
            $event->user->sendEmailVerificationNotification();
        }
    }
}
