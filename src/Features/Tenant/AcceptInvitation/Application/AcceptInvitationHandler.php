<?php

namespace Features\Tenant\AcceptInvitation\Application;

use App\Models\Invitation;
use App\Models\Role;
use App\Models\User;
use Features\Tenant\AcceptInvitation\Events\InvitationAccepted;
use Features\Tenant\Shared\Enums\InvitationStatus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AcceptInvitationHandler
{
    /**
     * @throws InvalidArgumentException
     */
    public function __invoke(AcceptInvitation $command): Invitation
    {
        $invitation = DB::transaction(function () use ($command) {
            // Lock the row so that two concurrent requests cannot both accept it.
            /** @var Invitation $invitation */
            $invitation = Invitation::query()
                ->where('token', $command->token)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $invitation->canBeAccepted()) {
                // Persist the expiry (it must survive the failure, so the
                // transaction is committed instead of rolled back by a throw).
                if ($invitation->isExpired()) {
                    $invitation->markAsExpired();
                }

                return null;
            }

            if (User::normalizeEmail($command->user->email) !== User::normalizeEmail($invitation->email)) {
                throw new InvalidArgumentException('This invitation is for a different email address.', 422);
            }

            $organization = $invitation->organization;

            if (! $organization) {
                throw new InvalidArgumentException('The organization of this invitation no longer exists.', 422);
            }

            if ($organization->hasUser($command->user)) {
                throw new InvalidArgumentException('You are already a member of this organization.', 422);
            }

            $roleExists = Role::query()->withoutOrganization()->whereKey($invitation->role_id)->exists();

            if (! $roleExists) {
                throw new InvalidArgumentException('The role of this invitation no longer exists. Please ask for a new invitation.', 422);
            }

            $organization->addUser($command->user, $invitation->role_id);

            $invitation->update([
                'status' => InvitationStatus::ACCEPTED->value,
                'accepted_at' => now(),
            ]);

            return $invitation;
        });

        if ($invitation === null) {
            throw new InvalidArgumentException('This invitation cannot be accepted.', 422);
        }

        event(new InvitationAccepted($invitation, $command->user));

        return $invitation->fresh();
    }
}
