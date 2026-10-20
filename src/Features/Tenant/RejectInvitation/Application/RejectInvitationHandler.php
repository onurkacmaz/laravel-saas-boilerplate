<?php

namespace Features\Tenant\RejectInvitation\Application;

use App\Models\Invitation;
use App\Models\User;
use Features\Tenant\RejectInvitation\Events\InvitationRejected;
use Features\Tenant\Shared\Enums\InvitationStatus;
use InvalidArgumentException;

class RejectInvitationHandler
{
    /**
     * @throws InvalidArgumentException
     */
    public function __invoke(RejectInvitation $command): Invitation
    {
        $invitation = Invitation::query()->where('token', $command->token)->firstOrFail();

        if (! $invitation->canBeAccepted()) {
            throw new InvalidArgumentException('This invitation cannot be rejected.', 422);
        }

        if ($command->user && User::normalizeEmail($command->user->email) !== User::normalizeEmail($invitation->email)) {
            throw new InvalidArgumentException('This invitation is for a different email address.', 422);
        }

        $invitation->update([
            'status' => InvitationStatus::REJECTED->value,
        ]);

        event(new InvitationRejected($invitation));

        return $invitation->fresh();
    }
}
