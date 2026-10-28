<?php

namespace Features\Subscription\Infrastructure\Eloquent\Models;

use App\Models\Organization;
use Features\Subscription\Infrastructure\Eloquent\Factories\SubscriptionFactory;
use Features\Subscription\Shared\Contracts\BillingSubscription;
use Features\Subscription\Shared\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Cashier\Subscription as CashierSubscription;
use Stripe\Subscription as StripeSubscription;

/**
 * @property string $id
 * @property string $organization_id
 * @property string|null $plan_id
 * @property string $name
 * @property string|null $stripe_id
 * @property string|null $stripe_status
 * @property string|null $stripe_price
 * @property int|null $quantity
 * @property bool $cancel_at_period_end
 * @property Carbon|null $cancel_at
 * @property Carbon|null $resume_at
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $canceled_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property Plan $plan
 * @property Organization $organization
 */
class Subscription extends CashierSubscription implements BillingSubscription
{
    use HasFactory, HasUuids, SoftDeletes;

    public const string LOCAL_SUBSCRIPTION_PREFIX = 'local_sub_';

    protected $guarded = [];

    protected $keyType = 'string';

    public $incrementing = false;

    protected static function newFactory(): SubscriptionFactory
    {
        return SubscriptionFactory::new();
    }

    protected function casts(): array
    {
        return [
            'cancel_at' => 'datetime',
            'resume_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'canceled_at' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'quantity' => 'integer',
        ];
    }

    public function ownerKeyName(): string
    {
        return 'organization_id';
    }

    public function owner(): BelongsTo
    {
        return $this->organization();
    }

    public function user(): BelongsTo
    {
        return $this->organization();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isActive(): bool
    {
        return $this->active();
    }

    public function isTrialing(): bool
    {
        return $this->onTrial();
    }

    public function isCanceled(): bool
    {
        return $this->canceled();
    }

    public function isExpired(): bool
    {
        return $this->ended();
    }

    public function isPastDue(): bool
    {
        return $this->pastDue();
    }

    public function hasValidSubscription(): bool
    {
        return $this->valid();
    }

    public function remainingDays(): int
    {
        $endsAt = $this->ends_at ?? $this->trial_ends_at;

        if (! $endsAt) {
            return 0;
        }

        return max(0, (int) ceil(now()->diffInDays($endsAt, false)));
    }

    public function getStatusAttribute(): SubscriptionStatus
    {
        return SubscriptionStatus::fromStripeStatus($this->stripe_status);
    }

    public function subscriptionStatus(): SubscriptionStatus
    {
        return $this->status;
    }

    public function provider(): string
    {
        return $this->isLocal() ? 'local' : 'stripe';
    }

    public function providerId(): string
    {
        return $this->stripe_id;
    }

    public function providerStatus(): ?string
    {
        return $this->stripe_status;
    }

    public static function makeLocalStripeId(): string
    {
        return self::LOCAL_SUBSCRIPTION_PREFIX.Str::uuid();
    }

    public function isLocal(): bool
    {
        return str_starts_with((string) $this->stripe_id, self::LOCAL_SUBSCRIPTION_PREFIX);
    }

    /** Free plans, which never reach a payment provider. */
    public function scopeLocal(Builder $query): void
    {
        $query->where('stripe_id', 'like', self::LOCAL_SUBSCRIPTION_PREFIX.'%');
    }

    /** Stripe subscriptions. */
    public function scopeNotLocal(Builder $query): void
    {
        $query->whereNot('stripe_id', 'like', self::LOCAL_SUBSCRIPTION_PREFIX.'%');
    }

    public function cancel(): Subscription|static
    {
        if ($this->isLocal()) {
            return $this->cancelLocally();
        }

        return parent::cancel();
    }

    public function cancelNow(): Subscription|static
    {
        if ($this->isLocal()) {
            return $this->cancelLocally();
        }

        return parent::cancelNow();
    }

    protected function cancelLocally(): static
    {
        $this->fill([
            'stripe_status' => StripeSubscription::STATUS_CANCELED,
            'cancel_at_period_end' => false,
            'cancel_at' => null,
            'ends_at' => now(),
            'canceled_at' => now(),
        ])->save();

        return $this;
    }
}
