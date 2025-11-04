<?php

namespace Features\Tenant\AcceptInvitation\Application;

use App\Models\Invitation;
use Features\Tenant\AcceptInvitation\Events\InvitationAccepted;
use Features\Tenant\Shared\Enums\InvitationStatus;
use InvalidArgumentException;

class AcceptInvitationHandler
{
    public function __invoke(AcceptInvitation $command): Invitation
    {
        $invitation = Invitation::query()->where('token', $command->token)->firstOrFail();

        if (! $invitation->canBeAccepted()) {
            throw new InvalidArgumentException(
                message: 'This invitation cannot be accepted.',
                code: 422
            );
        }

        if ($command->user->email !== $invitation->email) {
            throw new InvalidArgumentException(
                message: 'This invitation is for a different email address.',
                code: 422
            );
        }

        $invitation->organization->addUser($command->user, $invitation->role_id);

        $invitation->update([
            'status' => InvitationStatus::ACCEPTED->value,
            'accepted_at' => now(),
        ]);

        event(new InvitationAccepted($invitation, $command->user));

        return $invitation->fresh();
    }
}
