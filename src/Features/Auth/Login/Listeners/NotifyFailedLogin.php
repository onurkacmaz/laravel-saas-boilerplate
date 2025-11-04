<?php

namespace Features\Auth\Login\Listeners;

use Features\Auth\Login\Events\LoginFailed;

class NotifyFailedLogin
{
    /**
     * Handle the event.
     */
    public function handle(LoginFailed $event): void {}
}
