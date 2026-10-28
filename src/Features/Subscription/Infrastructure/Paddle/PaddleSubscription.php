<?php

namespace Features\Subscription\Infrastructure\Paddle;

use App\Models\Organization;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Shared\Contracts\BillingSubscription;
use Features\Subscription\Shared\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Laravel\Paddle\Subscription;

/**
 * A subscription billed by Paddle, through Cashier Paddle.
 *
 * The attributes the application reads on every subscription (organization_id,
 * starts_at, canceled_at...) are derived from Cashier Paddle's columns.
 *
 * @property int $id
 * @property string $billable_id
 * @property string|null $plan_id
 * @property string $paddle_id
 * @property string $status
 * @property Carbon|null $trial_ends_at
 * @property bool $had_trial
 * @property Carbon|null $paused_at
 * @property Carbon|null $ends_at
 * @property Carbon $created_at
 * @property-read string $organization_id
 * @property Plan|null $plan
 * @property Organization|null $organization
 */
class PaddleSubscription extends Subscription implements BillingSubscription
{
    protected $table = 'paddle_subscriptions';

    protected function casts(): array
    {
        return [
            'had_trial' => 'boolean',
        ];
    }

    /**
     * Cashier Paddle's items and transactions refer to their subscription by
     * subscription_id, not by the name Eloquent derives from this class.
     */
    public function getForeignKey(): string
    {
        return 'subscription_id';
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'billable_id');
    }

    public function provider(): string
    {
        return 'paddle';
    }

    public function providerId(): string
    {
        return $this->paddle_id;
    }

    public function providerStatus(): ?string
    {
        return $this->status;
    }

    public function subscriptionStatus(): SubscriptionStatus
    {
        if ($this->isExpired()) {
            return SubscriptionStatus::EXPIRED;
        }

        return match ($this->status) {
            self::STATUS_TRIALING => SubscriptionStatus::TRIALING,
            self::STATUS_ACTIVE => SubscriptionStatus::ACTIVE,
            self::STATUS_CANCELED => SubscriptionStatus::CANCELED,
            default => SubscriptionStatus::PAST_DUE,
        };
    }

    public function isLocal(): bool
    {
        return false;
    }

    /** Active or trialing, including a cancellation that waits for the end of the period. */
    public function isActive(): bool
    {
        return $this->active() || $this->onTrial();
    }

    public function isTrialing(): bool
    {
        return $this->onTrial();
    }

    /** Canceled, or scheduled to be at the end of the period (like Cashier's canceled()). */
    public function isCanceled(): bool
    {
        return $this->ends_at !== null || $this->canceled();
    }

    public function isExpired(): bool
    {
        return $this->isCanceled() && ! $this->onGracePeriod();
    }

    public function markAsCanceled(): static
    {
        $this->forceFill([
            'status' => self::STATUS_CANCELED,
            'ends_at' => $this->ends_at ?? now(),
        ])->save();

        return $this;
    }

    protected function organizationId(): Attribute
    {
        return Attribute::get(fn () => $this->billable_id);
    }

    protected function startsAt(): Attribute
    {
        return Attribute::get(fn () => $this->created_at);
    }

    protected function cancelAtPeriodEnd(): Attribute
    {
        return Attribute::get(fn () => (bool) $this->onGracePeriod());
    }

    protected function cancelAt(): Attribute
    {
        return Attribute::get(fn () => $this->onGracePeriod() ? $this->ends_at : null);
    }

    protected function canceledAt(): Attribute
    {
        return Attribute::get(fn () => $this->canceled() ? $this->ends_at : null);
    }

    protected function quantity(): Attribute
    {
        return Attribute::get(fn () => $this->items->first()?->quantity);
    }
}
