<?php

namespace Features\Tenant\CancelInvitation\Events;

use App\Models\Invitation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvitationCanceled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Invitation $invitation
    ) {}
}
