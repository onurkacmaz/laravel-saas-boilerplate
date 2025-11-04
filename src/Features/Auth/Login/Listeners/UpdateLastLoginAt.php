<?php

namespace Features\Auth\Login\Listeners;

use Features\Auth\Login\Events\LoginSucceeded;

class UpdateLastLoginAt
{
    /**
     * Handle the event.
     */
    public function handle(LoginSucceeded $event): void
    {
        $event->user->update(['last_login_at' => now()]);
    }
}
