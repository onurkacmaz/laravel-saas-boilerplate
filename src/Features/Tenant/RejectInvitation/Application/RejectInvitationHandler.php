<?php

namespace Features\Tenant\RejectInvitation\Application;

use App\Models\Invitation;
use Features\Tenant\RejectInvitation\Events\InvitationRejected;
use Features\Tenant\Shared\Enums\InvitationStatus;
use InvalidArgumentException;

class RejectInvitationHandler
{
    public function __invoke(RejectInvitation $command): Invitation
    {
        $invitation = Invitation::query()->where('token', $command->token)->firstOrFail();

        if (! $invitation->canBeAccepted()) {
            throw new InvalidArgumentException('This invitation cannot be rejected.');
        }

        $invitation->update([
            'status' => InvitationStatus::REJECTED->value,
        ]);

        event(new InvitationRejected($invitation));

        return $invitation->fresh();
    }
}
