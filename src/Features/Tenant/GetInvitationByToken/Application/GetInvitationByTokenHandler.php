<?php

namespace Features\Tenant\GetInvitationByToken\Application;

use App\Models\Invitation;

class GetInvitationByTokenHandler
{
    public function __invoke(GetInvitationByToken $command): Invitation
    {
        $invitation = Invitation::query()
            ->where('token', $command->token)
            ->with(['organization', 'role'])
            ->firstOrFail();

        if ($invitation->isExpired()) {
            $invitation->markAsExpired();
        }

        return $invitation;
    }
}
