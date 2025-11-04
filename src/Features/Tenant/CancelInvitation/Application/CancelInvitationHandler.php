<?php

namespace Features\Tenant\CancelInvitation\Application;

use App\Models\Invitation;
use Features\Tenant\CancelInvitation\Events\InvitationCanceled;
use Features\Tenant\Shared\Enums\InvitationStatus;
use Infrastructure\Tenant\TenantContext;
use InvalidArgumentException;

class CancelInvitationHandler
{
    public function __invoke(CancelInvitation $command): Invitation
    {
        $invitation = Invitation::query()->findOrFail($command->invitationId);

        $organization = TenantContext::getOrganization();

        if (! $organization || $invitation?->organization_id !== $organization->id) {
            throw new InvalidArgumentException(
                message: 'You do not have permission to cancel this invitation',
                code: 403
            );
        }

        if (! $invitation?->isPending()) {
            throw new InvalidArgumentException(
                message: 'Only pending invitations can be canceled.',
                code: 400
            );
        }

        $invitation?->update([
            'status' => InvitationStatus::CANCELED->value,
        ]);

        event(new InvitationCanceled($invitation));

        return $invitation?->fresh();
    }
}
