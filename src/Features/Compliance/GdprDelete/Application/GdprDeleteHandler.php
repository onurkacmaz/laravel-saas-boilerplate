<?php

namespace Features\Compliance\GdprDelete\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GdprDeleteHandler
{
    public function __invoke(GdprDelete $command): void
    {
        $user = $command->user;

        DB::transaction(static function () use ($user) {
            Log::info('Starting GDPR data deletion', ['user_id' => $user->id]);

            // Delete API tokens
            $user->tokens()->delete();

            // Delete audit logs
            $user->auditLogs()->delete();

            // Remove from organizations (but don't delete the organizations)
            $user->organizations()->detach();

            // Delete owned organizations if user is the sole owner
            foreach ($user->ownedOrganizations as $organization) {
                // Check if there are other admins
                $adminsCount = $organization->members()
                    ->wherePivot('role', 'admin')
                    ->count();

                if ($adminsCount <= 1) {
                    // Cancel subscriptions
                    $organization->subscriptions()->each(function ($subscription) {
                        if ($subscription->active()) {
                            $subscription->cancelNow();
                        }
                    });

                    // Delete organization data
                    $organization->webhooks()->delete();
                    $organization->invitations()->delete();
                    $organization->usages()->delete();
                    $organization->members()->detach();
                    $organization->delete();
                } else {
                    // Transfer ownership to another admin
                    $newOwner = $organization->members()
                        ->wherePivot('role', 'admin')
                        ->where('users.id', '!=', $user->id)
                        ->first();

                    if ($newOwner) {
                        $organization->update(['owner_id' => $newOwner->id]);
                    }
                }
            }

            // Anonymize user data instead of hard delete for audit trail
            $user->update([
                'name' => 'Deleted User',
                'email' => 'deleted-'.$user->id.'@deleted.local',
                'password' => bcrypt(str()->random(64)),
                'email_verified_at' => null,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ]);

            // Soft delete the user
            $user->delete();

            Log::info('GDPR data deletion completed', ['user_id' => $user->id]);
        });
    }
}
