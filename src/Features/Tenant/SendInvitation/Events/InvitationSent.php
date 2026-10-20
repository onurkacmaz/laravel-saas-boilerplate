<?php

namespace Features\Tenant\SendInvitation\Events;

use App\Models\Invitation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvitationSent
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Invitation $invitation
    ) {
        //
    }
}
