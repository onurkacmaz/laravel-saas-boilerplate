<?php

namespace Features\Compliance\GdprRequestDeletion\Application;

use Illuminate\Support\Carbon;

class GdprRequestDeletionHandler
{
    /**
     * Schedule the account for deletion. Returns the date the account will be
     * permanently deleted. Requesting again keeps the original schedule.
     */
    public function __invoke(GdprRequestDeletion $command): Carbon
    {
        $user = $command->user;

        if (! $user->deletion_requested_at) {
            $user->forceFill(['deletion_requested_at' => now()])->save();
        }

        return $user->deletion_requested_at->copy()->addDays((int) config('gdpr.deletion_grace_days', 30));
    }
}
