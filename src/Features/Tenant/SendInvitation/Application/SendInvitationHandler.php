<?php

namespace Features\Tenant\SendInvitation\Application;

use App\Models\Invitation;
use App\Models\User;
use Features\Tenant\SendInvitation\Events\InvitationSent;
use Features\Tenant\Shared\Enums\InvitationStatus;
use InvalidArgumentException;

class SendInvitationHandler
{
    public function __invoke(SendInvitation $command): Invitation
    {
        $existingUser = User::query()->where('email', $command->email)->first();
        if ($existingUser && $command->organization->hasUser($existingUser)) {
            throw new InvalidArgumentException(
                message: 'User is already a member of this organization.',
                code: 422
            );
        }

        $hasPendingInvitation = Invitation::query()
            ->where('organization_id', $command->organization->id)
            ->where('email', $command->email)
            ->where('status', InvitationStatus::PENDING->value)
            ->exists();

        if ($hasPendingInvitation) {
            throw new InvalidArgumentException(
                message: 'An invitation for this email is already pending.',
                code: 422
            );
        }

        $invitation = Invitation::query()->create([
            'organization_id' => $command->organization->id,
            'email' => $command->email,
            'invited_by' => $command->invitedBy?->id,
            'role_id' => $command->roleId,
            'status' => InvitationStatus::PENDING->value,
            'expires_at' => now()->addDays(7),
        ]);

        event(new InvitationSent($invitation));

        return $invitation;
    }
}
