<?php

namespace Features\Subscription\Shared\Contracts;

use App\Models\Organization;
use Carbon\CarbonInterface;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Subscribe\Application\CheckoutSession;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The payment provider paid plans are billed with (config billing.provider).
 * Free plans are handled by the application and never reach it.
 */
interface BillingGateway
{
    /** "stripe" or "paddle". */
    public function name(): string;

    /** The provider's price of the plan, or null when the plan is not sold with this provider. */
    public function priceId(Plan $plan): ?string;

    /**
     * The organization's paid subscriptions with this provider, newest first.
     *
     * @return Collection<int, BillingSubscription>
     */
    public function subscriptions(Organization $organization): Collection;

    /** The organization's active or trialing paid subscription. */
    public function activeSubscription(Organization $organization): ?BillingSubscription;

    /**
     * The trialing subscriptions, of every organization, whose trial ends in
     * the given window.
     *
     * @return Collection<int, BillingSubscription>
     */
    public function trialsEndingBetween(CarbonInterface $from, CarbonInterface $to): Collection;

    /** Whether a paid subscription of the organization ever had a trial. */
    public function hasUsedTrial(Organization $organization): bool;

    /**
     * Start paying for a plan: a checkout page where the organization enters
     * its payment details. The subscription is created by the provider's
     * webhook once paid.
     *
     * @param  array<string, string>  $metadata  attached to the subscription
     */
    public function checkout(Organization $organization, Plan $plan, bool $withTrial, ?int $quantity, array $metadata): CheckoutSession;

    /**
     * Move a paid subscription to another paid plan, prorated.
     *
     * @throws ValidationException when the payment is declined: nothing changes
     */
    public function swap(BillingSubscription $subscription, Plan $plan): void;

    /**
     * The address of the provider's customer portal.
     *
     * @throws NotFoundHttpException when the organization never paid
     */
    public function portalUrl(Organization $organization, string $returnUrl): string;
}
