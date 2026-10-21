<?php

namespace Features\Subscription\Shared\Listeners;

use App\Models\Organization;
use Features\Subscription\Shared\Events\OrganizationSubscriptionChanged;
use Features\Subscription\Shared\Events\SubscriptionCanceled;
use Features\Subscription\Shared\Events\SubscriptionCreated;
use Features\Subscription\Shared\Events\SubscriptionUpdated;
use Illuminate\Events\Dispatcher;

/**
 * Raises OrganizationSubscriptionChanged, with the plan now in effect, after
 * every subscription event.
 */
class DispatchOrganizationSubscriptionChanged
{
    public function subscribe(Dispatcher $events): array
    {
        return [
            SubscriptionCreated::class => 'handle',
            SubscriptionUpdated::class => 'handle',
            SubscriptionCanceled::class => 'handle',
        ];
    }

    public function handle(SubscriptionCreated|SubscriptionUpdated|SubscriptionCanceled $event): void
    {
        $organization = Organization::query()->find($event->subscription->getAttribute('organization_id'));

        if (! $organization) {
            return;
        }

        event(new OrganizationSubscriptionChanged($organization, $organization->activeSubscription()?->plan));
    }
}
