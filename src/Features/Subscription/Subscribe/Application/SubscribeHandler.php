<?php

namespace Features\Subscription\Subscribe\Application;

use App\Models\Organization;
use Exception;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Infrastructure\Eloquent\Models\Subscription as SubscriptionModel;
use Features\Subscription\Shared\Contracts\BillingGateway;
use Features\Subscription\Shared\Contracts\BillingSubscription;
use Features\Subscription\Shared\Events\SubscriptionCanceled;
use Features\Subscription\Shared\Events\SubscriptionCreated;
use Features\Subscription\Shared\Events\SubscriptionUpdated;
use Features\Subscription\Shared\Events\SubscriptionUpgraded;
use Features\Subscription\Shared\Support\BillingUrls;
use Illuminate\Validation\ValidationException;
use Infrastructure\Tenant\TenantContext;
use InvalidArgumentException;
use Throwable;

class SubscribeHandler
{
    public function __construct(
        private readonly BillingGateway $gateway,
    ) {}

    /**
     * @throws ValidationException
     * @throws Exception
     */
    public function __invoke(Subscribe $command): CheckoutSession
    {
        $organization = $command->organization ?? TenantContext::getOrganization();

        if (! $organization) {
            throw new InvalidArgumentException('Organization context is required.');
        }

        $plan = Plan::query()->findOrFail($command->planId);

        if (! $plan->is_active) {
            throw ValidationException::withMessages([
                'plan_id' => __('This plan is not currently available.'),
            ]);
        }

        $subscriptionName = 'default';
        $activeSubscription = $this->currentSubscription($organization);

        if ($activeSubscription && $activeSubscription->valid() && ! $activeSubscription->onGracePeriod()
            && $activeSubscription->plan_id === $plan->id) {
            throw ValidationException::withMessages([
                'plan_id' => __('The organization is already subscribed to this plan.'),
            ]);
        }

        // Trials are a one-time offer per organization: re-subscribing must not
        // grant a new free trial every time.
        $useTrial = $command->useTrial && $plan->trial_days > 0 && ! $this->hasUsedTrial($organization);

        $paidSubscription = $this->paidSubscription($activeSubscription);

        if ($plan->isFree()) {
            // A paid period is never cut short: the free plan takes over when it ends.
            if ($paidSubscription) {
                return $this->downgradeAtPeriodEnd($organization, $paidSubscription);
            }

            return $this->subscribeToFreePlan(
                organization: $organization,
                plan: $plan,
                command: $command,
                useTrial: $useTrial,
                subscriptionName: $subscriptionName,
                activeSubscription: $activeSubscription
            );
        }

        if (! $this->gateway->priceId($plan)) {
            throw ValidationException::withMessages([
                'plan_id' => __('This plan is not currently available.'),
            ]);
        }

        // Switching between paid plans changes the price of the subscription in
        // place, so the provider prorates the unused time instead of a second
        // checkout starting a new subscription and the old one being canceled.
        if ($paidSubscription) {
            return $this->changePaidPlan($organization, $paidSubscription, $plan);
        }

        $metadata = array_filter([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'previous_subscription_id' => $activeSubscription?->getKey(),
            'previous_subscription_stripe_id' => $activeSubscription?->getAttribute('stripe_id'),
        ]);

        return $this->gateway->checkout($organization, $plan, $useTrial, $command->quantity, $metadata);
    }

    private function subscribeToFreePlan(
        Organization $organization,
        Plan $plan,
        Subscribe $command,
        bool $useTrial,
        string $subscriptionName,
        ?BillingSubscription $activeSubscription
    ): CheckoutSession {
        $this->cancelExistingSubscription($activeSubscription);

        $localStripeId = SubscriptionModel::makeLocalStripeId();

        $trialEndsAt = $useTrial ? now()->addDays($plan->trial_days) : null;

        /** @var SubscriptionModel $subscription */
        $subscription = $organization->subscriptions()->create([
            'type' => $subscriptionName,
            'name' => $subscriptionName,
            'plan_id' => $plan->id,
            'stripe_id' => $localStripeId,
            'stripe_status' => 'active',
            'stripe_price' => null,
            'quantity' => $command->quantity,
            'trial_ends_at' => $trialEndsAt,
            'starts_at' => now(),
        ]);

        event(new SubscriptionCreated($subscription->fresh(['plan'])));

        return new CheckoutSession(
            id: $localStripeId,
            url: BillingUrls::success($localStripeId),
            expiresAt: now()->addMinutes(10)->getTimestamp(),
            customerId: $organization->stripe_id,
            action: CheckoutSession::ACTION_ACTIVATED,
        );
    }

    /**
     * The organization's latest subscription, free or paid, whatever its status.
     */
    private function currentSubscription(Organization $organization): ?BillingSubscription
    {
        return collect([
            $this->gateway->subscriptions($organization)->first(),
            $organization->subscriptions()->local()->first(),
        ])->filter()->sortByDesc('created_at')->first();
    }

    /**
     * The organization's running paid subscription, if it pays for one.
     */
    private function paidSubscription(?BillingSubscription $subscription): ?BillingSubscription
    {
        if (! $subscription || $subscription->isLocal()) {
            return null;
        }

        return $subscription->isActive() || $subscription->isTrialing() ? $subscription : null;
    }

    /**
     * Cancel the paid subscription at the end of its period. When the provider
     * ends it, the webhook puts the organization on the free plan.
     */
    private function downgradeAtPeriodEnd(Organization $organization, BillingSubscription $subscription): CheckoutSession
    {
        if (! $subscription->onGracePeriod()) {
            $subscription->cancel();

            event(new SubscriptionCanceled($subscription->fresh(['plan'])));
        }

        $id = $subscription->providerId();

        return new CheckoutSession(
            id: $id,
            url: BillingUrls::success($id),
            expiresAt: now()->addMinutes(10)->getTimestamp(),
            customerId: $organization->stripe_id,
            action: CheckoutSession::ACTION_SCHEDULED,
        );
    }

    /**
     * Move a paid subscription to another paid plan. The provider prorates the
     * change; when it bills at once (another interval) and the payment fails,
     * nothing changes.
     *
     * @throws ValidationException
     */
    private function changePaidPlan(Organization $organization, BillingSubscription $subscription, Plan $plan): CheckoutSession
    {
        $oldPlan = $subscription->plan;

        $this->gateway->swap($subscription, $plan);

        $subscription->forceFill(['plan_id' => $plan->id])->save();
        $subscription = $subscription->fresh(['plan']);

        event(new SubscriptionUpdated($subscription));

        if ($oldPlan && $plan->price > $oldPlan->price) {
            event(new SubscriptionUpgraded($subscription, $oldPlan, $plan));
        }

        $id = $subscription->providerId();

        return new CheckoutSession(
            id: $id,
            url: BillingUrls::success($id),
            expiresAt: now()->addMinutes(10)->getTimestamp(),
            customerId: $organization->stripe_id,
            action: CheckoutSession::ACTION_ACTIVATED,
        );
    }

    private function hasUsedTrial(Organization $organization): bool
    {
        return $organization->subscriptions()
            ->withTrashed()
            ->local()
            ->whereNotNull('trial_ends_at')
            ->exists()
            || $this->gateway->hasUsedTrial($organization);
    }

    private function cancelExistingSubscription(?BillingSubscription $subscription): void
    {
        if (! $subscription || $subscription->isCanceled()) {
            return;
        }

        try {
            $subscription->cancelNow();
        } catch (Throwable $exception) {
            report($exception);

            $subscription->markAsCanceled();
        }
    }
}
