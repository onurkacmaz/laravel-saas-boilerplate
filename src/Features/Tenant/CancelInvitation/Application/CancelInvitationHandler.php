<?php

namespace Features\Tenant\CancelInvitation\Application;

use App\Models\Invitation;
use Features\Tenant\CancelInvitation\Events\InvitationCanceled;
use Features\Tenant\Shared\Enums\InvitationStatus;
use Infrastructure\Tenant\TenantContext;
use InvalidArgumentException;

class CancelInvitationHandler
{
    /**
     * @throws InvalidArgumentException
     */
    public function __invoke(CancelInvitation $command): Invitation
    {
        $organization = TenantContext::getOrganization();

        if (! $organization) {
            throw new InvalidArgumentException('You do not have permission to cancel this invitation', 403);
        }

        // Scoped to the current organization: invitations of other tenants are
        // reported as not found instead of leaking their existence.
        $invitation = Invitation::query()
            ->where('organization_id', $organization->id)
            ->findOrFail($command->invitationId);

        if (! $invitation->isPending()) {
            throw new InvalidArgumentException('Only pending invitations can be canceled.', 422);
        }

        $invitation->update([
            'status' => InvitationStatus::CANCELED->value,
        ]);

        event(new InvitationCanceled($invitation));

        return $invitation->fresh();
    }
}
