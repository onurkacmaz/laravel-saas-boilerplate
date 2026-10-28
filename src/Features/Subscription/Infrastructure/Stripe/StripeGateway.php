<?php

namespace Features\Subscription\Infrastructure\Stripe;

use App\Models\Organization;
use Carbon\CarbonInterface;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
use Features\Subscription\Shared\Contracts\BillingGateway;
use Features\Subscription\Shared\Contracts\BillingSubscription;
use Features\Subscription\Shared\Support\BillingUrls;
use Features\Subscription\Subscribe\Application\CheckoutSession;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\CardException;
use Stripe\Subscription as StripeSubscription;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Stripe through Laravel Cashier. Its subscriptions share the subscriptions
 * table with the free plans.
 */
class StripeGateway implements BillingGateway
{
    public function name(): string
    {
        return 'stripe';
    }

    public function priceId(Plan $plan): ?string
    {
        return $plan->getStripePriceId();
    }

    public function subscriptions(Organization $organization): Collection
    {
        return $organization->subscriptions()->notLocal()->get();
    }

    public function activeSubscription(Organization $organization): ?BillingSubscription
    {
        return $organization->subscriptions()
            ->notLocal()
            ->whereIn('stripe_status', [StripeSubscription::STATUS_ACTIVE, StripeSubscription::STATUS_TRIALING])
            ->with('plan')
            ->first();
    }

    public function trialsEndingBetween(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Subscription::query()
            ->notLocal()
            ->where('stripe_status', StripeSubscription::STATUS_TRIALING)
            ->whereBetween('trial_ends_at', [$from, $to])
            ->with(['organization.owner', 'plan'])
            ->get();
    }

    public function hasUsedTrial(Organization $organization): bool
    {
        return $organization->subscriptions()
            ->withTrashed()
            ->notLocal()
            ->whereNotNull('trial_ends_at')
            ->exists();
    }

    public function checkout(Organization $organization, Plan $plan, bool $withTrial, ?int $quantity, array $metadata): CheckoutSession
    {
        $builder = $organization->newSubscription('default', $this->priceId($plan))
            ->withMetadata($metadata);

        if ($withTrial) {
            $builder->trialDays($plan->trial_days);
        } else {
            $builder->skipTrial();
        }

        if ($quantity !== null) {
            $builder->quantity($quantity);
        }

        $checkout = $builder->checkout([
            'success_url' => BillingUrls::frontend().'/subscriptions/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => BillingUrls::cancel(),
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

    public function swap(BillingSubscription $subscription, Plan $plan): void
    {
        /** @var Subscription $subscription */
        try {
            $subscription->errorIfPaymentFails()->swap($this->priceId($plan));
        } catch (CardException) {
            throw ValidationException::withMessages([
                'plan_id' => __('Your card was declined. Update your payment method and try again.'),
            ]);
        }
    }

    public function portalUrl(Organization $organization, string $returnUrl): string
    {
        // Free plans never reach Stripe, so there is no customer to manage.
        if (! $organization->hasStripeId()) {
            throw new NotFoundHttpException(__('There is no billing account yet: subscribe to a paid plan first.'));
        }

        return $organization->billingPortalUrl($returnUrl);
    }
}
