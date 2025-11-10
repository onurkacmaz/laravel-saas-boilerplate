<?php

namespace Features\Subscription\Subscribe\Application;

use App\Models\Organization;
use Exception;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Infrastructure\Eloquent\Models\Subscription as SubscriptionModel;
use Features\Subscription\Shared\Events\SubscriptionCreated;
use Illuminate\Validation\ValidationException;
use Infrastructure\Tenant\TenantContext;
use InvalidArgumentException;
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

        if ($plan->isFree()) {
            return $this->subscribeToFreePlan(
                organization: $organization,
                plan: $plan,
                command: $command,
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

        $metadata = array_filter([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'previous_subscription_id' => $activeSubscription?->id,
            'previous_subscription_stripe_id' => $activeSubscription?->stripe_id,
        ]);

        $builder = $organization->newSubscription($subscriptionName, $stripePrice)
            ->withMetadata($metadata);

        if ($command->useTrial && $plan->trial_days) {
            $builder->trialDays($plan->trial_days);
        } else {
            $builder->skipTrial();
        }

        if ($command->quantity !== null) {
            $builder->quantity($command->quantity);
        }

        $checkout = $builder->checkout([
            'success_url' => sprintf('%s/subscriptions/success?session_id={CHECKOUT_SESSION_ID}', config('app.frontend_url')),
            'cancel_url' => sprintf('%s/subscriptions/cancel', config('app.frontend_url')),
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
        string $subscriptionName,
        ?SubscriptionModel $activeSubscription
    ): CheckoutSession {
        $this->cancelExistingSubscription($activeSubscription);

        $localStripeId = SubscriptionModel::makeLocalStripeId();

        $trialEndsAt = ($command->useTrial && $plan->trial_days)
            ? now()->addDays($plan->trial_days)
            : null;

        /**
         * @var SubscriptionModel $subscription
         */
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

        $frontendUrl = rtrim(config('app.frontend_url') ?? config('app.url'), '/');
        $successUrl = sprintf('%s/subscriptions/success?session_id=%s', $frontendUrl, $localStripeId);

        return new CheckoutSession(
            id: $localStripeId,
            url: $successUrl,
            expiresAt: now()->addMinutes(10)->getTimestamp(),
            customerId: $organization->stripe_id
        );
    }

    private function cancelExistingSubscription(?SubscriptionModel $subscription): void
    {
        if (! $subscription) {
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
