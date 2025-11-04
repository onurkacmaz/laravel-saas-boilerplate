<?php

namespace Features\Auth\TwoFactorAuth\Listeners;

use Features\Auth\TwoFactorAuth\Events\TwoFactorDisabled;
use Features\Auth\TwoFactorAuth\Notifications\TwoFactorDisabledNotification;

class SendTwoFactorDisabledNotification
{
    /**
     * Handle the event.
     */
    public function handle(TwoFactorDisabled $event): void
    {
        $event->authenticatable->notify(new TwoFactorDisabledNotification($event->authenticatable));
    }
}
