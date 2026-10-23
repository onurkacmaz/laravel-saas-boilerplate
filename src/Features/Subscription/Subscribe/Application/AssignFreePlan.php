<?php

namespace Features\Subscription\Subscribe\Application;

use App\Models\Organization;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AssignFreePlan
{
    public function __construct(
        private readonly SubscribeHandler $subscribe,
    ) {}

    /**
     * The active free plan new organizations start on: the oldest one.
     */
    public static function freePlanId(): ?string
    {
        return Plan::query()
            ->where('is_active', true)
            ->where('price', 0)
            ->orderBy('created_at')
            ->value('id');
    }

    /**
     * Subscribe an organization without an active plan to the free plan, and
     * tell whether it was subscribed.
     *
     * A billing hiccup must never prevent an organization from being created,
     * so failures are reported rather than thrown. A missing free plan (the
     * plan catalogue was not seeded) is logged, so it does not go unnoticed.
     */
    public function __invoke(Organization $organization): bool
    {
        if ($organization->hasActiveSubscription()) {
            return false;
        }

        $planId = self::freePlanId();

        if (! $planId) {
            Log::warning('No active free plan exists, so the organization starts without a plan. Seed the plan catalogue (php artisan db:seed), then run php artisan subscriptions:assign-free-plan.', [
                'organization_id' => $organization->id,
            ]);

            return false;
        }

        try {
            // A savepoint: a failed query must not abort the caller's
            // transaction, e.g. the one of the registration.
            DB::transaction(fn () => ($this->subscribe)(
                command: new Subscribe(
                    planId: $planId,
                    useTrial: true,
                    quantity: 1,
                    organization: $organization
                )
            ));
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }

        return true;
    }
}
