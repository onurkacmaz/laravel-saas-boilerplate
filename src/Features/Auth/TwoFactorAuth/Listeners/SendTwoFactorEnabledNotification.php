<?php

namespace Features\Auth\TwoFactorAuth\Listeners;

use Features\Auth\TwoFactorAuth\Events\TwoFactorEnabled;
use Features\Auth\TwoFactorAuth\Notifications\TwoFactorEnabledNotification;

class SendTwoFactorEnabledNotification
{
    /**
     * Handle the event.
     */
    public function handle(TwoFactorEnabled $event): void
    {
        $event->authenticatable->notify(new TwoFactorEnabledNotification($event->authenticatable));
    }
}
