<?php

namespace Features\Tenant\AcceptInvitation\Events;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvitationAccepted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Invitation $invitation,
        public User $user
    ) {}
}
