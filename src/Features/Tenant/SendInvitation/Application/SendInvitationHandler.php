<?php

namespace Features\Tenant\SendInvitation\Application;

use App\Models\Invitation;
use App\Models\User;
use Features\Tenant\SendInvitation\Events\InvitationSent;
use Features\Tenant\Shared\Enums\InvitationStatus;
use Features\Tenant\Shared\Support\OrganizationRoles;
use InvalidArgumentException;

class SendInvitationHandler
{
    /**
     * @throws InvalidArgumentException
     */
    public function __invoke(SendInvitation $command): Invitation
    {
        $organization = $command->organization;
        $email = User::normalizeEmail($command->email);

        if (User::normalizeEmail($command->invitedBy->email) === $email) {
            throw new InvalidArgumentException('You cannot invite yourself.', 422);
        }

        $existingUser = User::query()->where('email', $email)->first();

        if ($existingUser && $organization->hasUser($existingUser)) {
            throw new InvalidArgumentException('User is already a member of this organization.', 422);
        }

        $role = OrganizationRoles::assignable($organization, $command->roleId);

        // Pending invitations whose expiry date has passed no longer block a new one.
        Invitation::query()
            ->where('organization_id', $organization->id)
            ->where('email', $email)
            ->where('status', InvitationStatus::PENDING->value)
            ->where('expires_at', '<=', now())
            ->update(['status' => InvitationStatus::EXPIRED->value]);

        $hasPendingInvitation = Invitation::query()
            ->where('organization_id', $organization->id)
            ->where('email', $email)
            ->open()
            ->exists();

        if ($hasPendingInvitation) {
            throw new InvalidArgumentException('An invitation for this email is already pending.', 422);
        }

        $invitation = Invitation::query()->create([
            'organization_id' => $organization->id,
            'email' => $email,
            'invited_by' => $command->invitedBy->id,
            'role_id' => $role->id,
            'status' => InvitationStatus::PENDING->value,
            'expires_at' => now()->addDays((int) config('settings.invitations.expire_days', 7)),
        ]);

        event(new InvitationSent($invitation));

        return $invitation;
    }
}
