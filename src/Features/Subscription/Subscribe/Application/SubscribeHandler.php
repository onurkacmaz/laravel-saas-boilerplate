<?php

namespace Features\Subscription\Subscribe\Application;

use Exception;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Illuminate\Validation\ValidationException;
use Infrastructure\Tenant\TenantContext;

class SubscribeHandler
{
    /**
     * @throws ValidationException
     * @throws Exception
     */
    public function __invoke(Subscribe $command): CheckoutSession
    {
        $organization = TenantContext::getOrganization();
        $plan = Plan::query()->findOrFail($command->planId);

        if (! $plan->is_active) {
            throw ValidationException::withMessages([
                'plan_id' => __('This plan is not currently available.'),
            ]);
        }

        if (! $plan->getStripePriceId()) {
            throw ValidationException::withMessages([
                'plan_id' => __('This plan is not currently available.'),
            ]);
        }

        $stripePrice = $plan->getStripePriceId();
        $subscriptionName = 'default';

        $activeSubscription = $organization->subscription($subscriptionName);

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
}
