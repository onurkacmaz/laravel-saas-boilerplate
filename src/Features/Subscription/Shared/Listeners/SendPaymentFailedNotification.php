<?php

namespace Features\Subscription\Shared\Listeners;

use App\Models\Role;
use Features\Subscription\Shared\Events\PaymentFailed;
use Features\Subscription\Shared\Notifications\PaymentFailedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendPaymentFailedNotification implements ShouldQueue
{
    public function handle(PaymentFailed $event): void
    {
        $organization = $event->organization;

        // Send notification to an organization owner and admins
        if ($owner = $organization->owner) {
            $owner->notify(new PaymentFailedNotification($organization, $event->paymentData));
        }

        // Also notify the admins (the owner already got the notification).
        $adminRoleIds = Role::query()
            ->withoutOrganization()
            ->where('slug', 'admin')
            ->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $organization->id))
            ->pluck('id');

        $organization->users()
            ->wherePivotIn('role_id', $adminRoleIds)
            ->where('users.id', '!=', $organization->owner_id)
            ->get()
            ->each(fn ($member) => $member->notify(
                new PaymentFailedNotification($organization, $event->paymentData)
            ));
    }
}
