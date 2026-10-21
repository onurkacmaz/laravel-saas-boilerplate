<?php

namespace Features\Subscription\Shared\Events;

use App\Models\Organization;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An organization's subscriptions changed: one was created, updated (plan
 * switch, status change) or canceled. `plan` is the plan in effect now, or
 * null without an active subscription.
 *
 * Listen to it to bring a module's resources in line with the plan's limits,
 * e.g. after a downgrade. It can be raised when the plan did not change, so
 * listeners must be idempotent.
 */
class OrganizationSubscriptionChanged
{
    use Dispatchable;

    public function __construct(
        public Organization $organization,
        public ?Plan $plan,
    ) {}
}
