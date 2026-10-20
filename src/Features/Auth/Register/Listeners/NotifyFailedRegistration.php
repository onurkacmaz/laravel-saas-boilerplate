<?php

namespace Features\Auth\Register\Listeners;

use App\Models\User;
use Features\Auth\Register\Events\UserRegisterFailed;
use Features\Auth\Register\Notifications\FailedRegistrationNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotifyFailedRegistration
{
    /**
     * Let the platform administrators know that a registration failed.
     */
    public function handle(UserRegisterFailed $event): void
    {
        try {
            User::query()->coreAdmins()->each(function (User $admin) use ($event) {
                $admin->notify(new FailedRegistrationNotification(
                    email: (string) ($event->credentials['email'] ?? ''),
                    name: (string) ($event->credentials['name'] ?? ''),
                    reason: (string) ($event->credentials['error'] ?? 'Unknown error'),
                ));
            });
        } catch (Throwable $e) {
            // Notifying admins must never mask the original registration error.
            Log::warning('Unable to notify administrators about a failed registration.', [
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
