<?php

namespace Features\Subscription\Shared\Contracts;

use Carbon\CarbonInterface;
use Features\Subscription\Shared\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A subscription of an organization, whichever provider bills it: the
 * subscriptions table holds free plans and Stripe subscriptions, Paddle
 * subscriptions live in their own table. The application only reads them
 * through this contract.
 *
 * Every implementation is an Eloquent model with the organization_id,
 * plan_id, trial_ends_at and ends_at attributes and a plan relation.
 *
 * @property string $organization_id
 * @property string|null $plan_id
 */
interface BillingSubscription
{
    /** "local" for a free plan, else the provider: "stripe" or "paddle". */
    public function provider(): string;

    /** The provider's identifier of the subscription (a local one for a free plan). */
    public function providerId(): string;

    /** The status as the provider names it, e.g. "trialing". */
    public function providerStatus(): ?string;

    public function subscriptionStatus(): SubscriptionStatus;

    public function plan(): BelongsTo;

    public function isLocal(): bool;

    public function isActive(): bool;

    public function isTrialing(): bool;

    public function isCanceled(): bool;

    public function isExpired(): bool;

    public function onGracePeriod();

    /**
     * Active, trialing, on its grace period (canceled, until the end of the
     * period) or on its payment grace period (past due, while the provider
     * retries the payment).
     */
    public function valid();

    /** When the subscription fell past due (a renewal payment failed), or null when it is not. */
    public function pastDueSince(): ?CarbonInterface;

    /** When the plan stops being in effect if the payment still fails, or null when not past due. */
    public function paymentGraceEndsAt(): ?CarbonInterface;

    /**
     * Past due for less than billing.past_due_grace_days: the plan stays in
     * effect while the provider retries the payment.
     */
    public function onPaymentGracePeriod(): bool;

    /** Record that the end of the payment grace period was reported. */
    public function markPaymentGraceEnded(): void;

    /** Cancel at the end of the current period. */
    public function cancel();

    public function cancelNow();

    /** Record a cancellation the provider could not be told about. */
    public function markAsCanceled();
}
