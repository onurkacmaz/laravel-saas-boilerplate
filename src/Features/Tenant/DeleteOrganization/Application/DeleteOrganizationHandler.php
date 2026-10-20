<?php

namespace Features\Tenant\DeleteOrganization\Application;

use App\Models\Organization;
use Features\Tenant\DeleteOrganization\Events\OrganizationDeleted;
use Illuminate\Support\Facades\DB;
use Throwable;

class DeleteOrganizationHandler
{
    public function __invoke(DeleteOrganization $command): void
    {
        $organization = Organization::query()->findOrFail($command->organizationId);

        // Stop billing first: a soft deleted organization must not keep paying
        // for a subscription nobody can use anymore.
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

        $deleted = DB::transaction(function () use ($organization) {
            $organization->webhooks()->update(['is_active' => false]);
            $organization->invitations()->delete();

            return $organization->delete();
        });

        if ($deleted) {
            event(new OrganizationDeleted($organization));
        }
    }
}
