<?php

namespace Features\Tenant\SendInvitation\Listeners;

use Features\Tenant\SendInvitation\Events\InvitationSent;
use Features\Tenant\SendInvitation\Notifications\OrganizationInvitation;
use Illuminate\Support\Facades\Notification;

class SendInvitationEmail
{
    /**
     * Handle the event.
     */
    public function handle(InvitationSent $event): void
    {
        Notification::route('mail', $event->invitation->email)
            ->notify(new OrganizationInvitation($event->invitation));
    }
}
