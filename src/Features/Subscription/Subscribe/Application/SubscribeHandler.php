<?php

namespace Features\Subscription\Subscribe\Application;

use App\Models\Organization;
use Exception;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Infrastructure\Eloquent\Models\Subscription as SubscriptionModel;
use Features\Subscription\Shared\Events\SubscriptionCanceled;
use Features\Subscription\Shared\Events\SubscriptionCreated;
use Features\Subscription\Shared\Events\SubscriptionUpdated;
use Features\Subscription\Shared\Events\SubscriptionUpgraded;
use Illuminate\Validation\ValidationException;
use Infrastructure\Tenant\TenantContext;
use InvalidArgumentException;
use Stripe\Exception\CardException;
use Stripe\Subscription as StripeSubscription;
use Throwable;

class SubscribeHandler
{
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
        $activeSubscription = $organization->subscription($subscriptionName);

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

        if (! $plan->getStripePriceId()) {
            throw ValidationException::withMessages([
                'plan_id' => __('This plan is not currently available.'),
            ]);
        }

        $stripePrice = $plan->getStripePriceId();

        // Switching between paid plans changes the price of the subscription in
        // place, so Stripe prorates the unused time instead of a second checkout
        // starting a new subscription and the old one being canceled outright.
        if ($paidSubscription) {
            return $this->changePaidPlan($organization, $paidSubscription, $plan, $stripePrice);
        }

        $metadata = array_filter([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'previous_subscription_id' => $activeSubscription?->id,
            'previous_subscription_stripe_id' => $activeSubscription?->stripe_id,
        ]);

        $builder = $organization->newSubscription($subscriptionName, $stripePrice)
            ->withMetadata($metadata);

        if ($useTrial) {
            $builder->trialDays($plan->trial_days);
        } else {
            $builder->skipTrial();
        }

        if ($command->quantity !== null) {
            $builder->quantity($command->quantity);
        }

        $frontendUrl = $this->frontendUrl();

        $checkout = $builder->checkout([
            'success_url' => sprintf('%s/subscriptions/success?session_id={CHECKOUT_SESSION_ID}', $frontendUrl),
            'cancel_url' => sprintf('%s/subscriptions/cancel', $frontendUrl),
            'client_reference_id' => $organization->id,
            'metadata' => $metadata,
        ], [
            'name' => $organization->name,
            'email' => $organization->stripeEmail(),
        ]);

        $session = $checkout->asStripeCheckoutSession();

        return new CheckoutSession(
            id: $session->id,
            url: $session->url,
            expiresAt: $session->expires_at,
            customerId: $session->customer ?? null
        );
    }

    private function subscribeToFreePlan(
        Organization $organization,
        Plan $plan,
        Subscribe $command,
        bool $useTrial,
        string $subscriptionName,
        ?SubscriptionModel $activeSubscription
    ): CheckoutSession {
        $this->cancelExistingSubscription($activeSubscription);

        $localStripeId = SubscriptionModel::makeLocalStripeId();

        $trialEndsAt = $useTrial ? now()->addDays($plan->trial_days) : null;

        /**
         * @var SubscriptionModel $subscription
         */
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
            url: $this->successUrl($localStripeId),
            expiresAt: now()->addMinutes(10)->getTimestamp(),
            customerId: $organization->stripe_id,
            action: CheckoutSession::ACTION_ACTIVATED,
        );
    }

    /**
     * The organization's running Stripe subscription, if it pays for one.
     */
    private function paidSubscription(?SubscriptionModel $subscription): ?SubscriptionModel
    {
        if (! $subscription || $subscription->isLocal()) {
            return null;
        }

        $running = in_array($subscription->stripe_status, [StripeSubscription::STATUS_ACTIVE, StripeSubscription::STATUS_TRIALING], true);

        return $running ? $subscription : null;
    }

    /**
     * Cancel the paid subscription at the end of its period. When Stripe ends
     * it, the webhook puts the organization on the free plan.
     */
    private function downgradeAtPeriodEnd(Organization $organization, SubscriptionModel $subscription): CheckoutSession
    {
        if (! $subscription->onGracePeriod()) {
            $subscription->cancel();

            event(new SubscriptionCanceled($subscription->fresh(['plan'])));
        }

        return new CheckoutSession(
            id: $subscription->stripe_id,
            url: $this->successUrl($subscription->stripe_id),
            expiresAt: now()->addMinutes(10)->getTimestamp(),
            customerId: $organization->stripe_id,
            action: CheckoutSession::ACTION_SCHEDULED,
        );
    }

    /**
     * Move a paid subscription to another paid plan. Stripe prorates the
     * change; when it bills at once (another interval) and the payment fails,
     * nothing changes.
     *
     * @throws ValidationException
     */
    private function changePaidPlan(Organization $organization, SubscriptionModel $subscription, Plan $plan, string $stripePrice): CheckoutSession
    {
        $oldPlan = $subscription->plan;

        try {
            $subscription->errorIfPaymentFails()->swap($stripePrice);
        } catch (CardException) {
            throw ValidationException::withMessages([
                'plan_id' => __('Your card was declined. Update your payment method and try again.'),
            ]);
        }

        $subscription->forceFill(['plan_id' => $plan->id])->save();
        $subscription = $subscription->fresh(['plan']);

        event(new SubscriptionUpdated($subscription));

        if ($oldPlan && $plan->price > $oldPlan->price) {
            event(new SubscriptionUpgraded($subscription, $oldPlan, $plan));
        }

        return new CheckoutSession(
            id: $subscription->stripe_id,
            url: $this->successUrl($subscription->stripe_id),
            expiresAt: now()->addMinutes(10)->getTimestamp(),
            customerId: $organization->stripe_id,
            action: CheckoutSession::ACTION_ACTIVATED,
        );
    }

    private function successUrl(string $sessionId): string
    {
        return sprintf('%s/subscriptions/success?session_id=%s', $this->frontendUrl(), $sessionId);
    }

    private function frontendUrl(): string
    {
        return rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/');
    }

    private function hasUsedTrial(Organization $organization): bool
    {
        return $organization->subscriptions()
            ->withTrashed()
            ->whereNotNull('trial_ends_at')
            ->exists();
    }

    private function cancelExistingSubscription(?SubscriptionModel $subscription): void
    {
        if (! $subscription || $subscription->canceled()) {
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
