<?php

namespace Features\Tenant\RejectInvitation\Events;

use App\Models\Invitation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvitationRejected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Invitation $invitation
    ) {}
}
