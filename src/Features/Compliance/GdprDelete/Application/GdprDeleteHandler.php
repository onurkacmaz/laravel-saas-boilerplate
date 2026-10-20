<?php

namespace Features\Compliance\GdprDelete\Application;

use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use Features\Acl\Shared\Support\SystemRoles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class GdprDeleteHandler
{
    /**
     * Erase a user's personal data.
     *
     * Owned organizations are handed over to another admin when there is one;
     * otherwise they are deleted (and their subscriptions cancelled). The user
     * record is anonymized and soft deleted so that foreign keys (audit
     * trails, invitations...) stay consistent.
     */
    public function __invoke(GdprDelete $command): void
    {
        $user = $command->user;

        Log::info('Starting GDPR data deletion', ['user_id' => $user->id]);

        $organizationsToDelete = [];
        $ownershipTransfers = [];

        foreach ($user->ownedOrganizations()->get() as $organization) {
            $successor = $this->findSuccessor($organization, $user);

            if ($successor) {
                $ownershipTransfers[] = [$organization, $successor];
            } else {
                $organizationsToDelete[] = $organization;
            }
        }

        // Talk to the payment provider before touching the database: a remote
        // call cannot be rolled back with the transaction.
        foreach ($organizationsToDelete as $organization) {
            $this->cancelSubscriptions($organization);
        }

        DB::transaction(function () use ($user, $organizationsToDelete, $ownershipTransfers) {
            foreach ($ownershipTransfers as [$organization, $successor]) {
                $organization->forceFill(['owner_id' => $successor->id])->save();
                $organization->addUser($successor, SystemRoles::ensure(SystemRoles::OWNER)->id);
            }

            foreach ($organizationsToDelete as $organization) {
                $organization->webhooks()->delete();
                $organization->invitations()->delete();
                $organization->users()->detach();
                $organization->delete();
            }

            $user->tokens()->delete();
            $user->roles()->detach();
            $user->organizations()->detach();
            $user->auditLogs()->update(['ip_address' => null, 'user_agent' => null]);

            // Invitations addressed to the user contain their e-mail address.
            Invitation::query()->where('email', $user->email)->forceDelete();

            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            $user->forceFill([
                'name' => 'Deleted User',
                'email' => 'deleted-'.$user->id.'@deleted.invalid',
                'password' => Str::random(64),
                'email_verified_at' => null,
                'remember_token' => null,
                'two_factor_enabled' => false,
                'two_factor_verified' => false,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'deletion_requested_at' => null,
            ])->save();

            $user->delete();
        });

        Log::info('GDPR data deletion completed', ['user_id' => $user->id]);
    }

    /**
     * Pick the admin who takes over an organization. Without one, the
     * organization is deleted together with the account.
     */
    private function findSuccessor(Organization $organization, User $user): ?User
    {
        $candidates = $organization->users()->where('users.id', '!=', $user->id)->get();

        return $candidates->first(fn (User $member) => $organization->isAdmin($member));
    }

    private function cancelSubscriptions(Organization $organization): void
    {
        foreach ($organization->subscriptions()->get() as $subscription) {
            if (! $subscription->valid()) {
                continue;
            }

            try {
                $subscription->cancelNow();
            } catch (Throwable $exception) {
                report($exception);
                $subscription->markAsCanceled();
            }
        }
    }
}
