<?php

namespace Features\Auth\Register\Listeners;

use App\Models\User;
use Features\Auth\Register\Events\UserRegisterFailed;
use Features\Auth\Register\Notifications\FailedRegistrationNotification;

class NotifyFailedRegistration
{
    /**
     * Handle the event.
     */
    public function handle(UserRegisterFailed $event): void
    {
        User::query()->coreAdmins()->each(function ($user) {
            $user->notify(new FailedRegistrationNotification($user));
        });
    }
}
