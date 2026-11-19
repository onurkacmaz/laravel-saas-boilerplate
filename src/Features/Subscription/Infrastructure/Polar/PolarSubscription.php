<?php

namespace Features\Subscription\Infrastructure\Polar;

use App\Models\Organization;
use Carbon\CarbonInterface;
use Features\Subscription\Infrastructure\Eloquent\Concerns\HasPaymentGracePeriod;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Shared\Contracts\BillingSubscription;
use Features\Subscription\Shared\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;

/**
 * A subscription billed by Polar. Its row mirrors the latest state Polar sent
 * (a webhook, or the answer to a change made here).
 *
 * The attributes the application reads on every subscription (starts_at,
 * canceled_at, quantity...) are derived from these columns.
 *
 * @property int $id
 * @property string $organization_id
 * @property string|null $plan_id
 * @property string $polar_id
 * @property string|null $polar_customer_id
 * @property string|null $polar_product_id
 * @property string $status
 * @property int|null $seats
 * @property Carbon|null $trial_ends_at
 * @property bool $had_trial
 * @property Carbon|null $current_period_end
 * @property Carbon|null $ends_at
 * @property Carbon|null $past_due_at
 * @property Carbon|null $payment_grace_ended_at
 * @property string|null $polar_modified_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Plan|null $plan
 * @property Organization|null $organization
 */
class PolarSubscription extends Model implements BillingSubscription
{
    use HasPaymentGracePeriod;

    public const string STATUS_INCOMPLETE = 'incomplete';

    public const string STATUS_INCOMPLETE_EXPIRED = 'incomplete_expired';

    public const string STATUS_TRIALING = 'trialing';

    public const string STATUS_ACTIVE = 'active';

    public const string STATUS_PAST_DUE = 'past_due';

    public const string STATUS_CANCELED = 'canceled';

    public const string STATUS_UNPAID = 'unpaid';

    public const string STATUS_PAUSED = 'paused';

    protected $table = 'polar_subscriptions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'seats' => 'integer',
            'trial_ends_at' => 'datetime',
            'had_trial' => 'boolean',
            'current_period_end' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    protected function paymentStatusColumn(): string
    {
        return 'status';
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Copy a Polar subscription (from a webhook or an API answer) onto the row.
     *
     * @param  array<string, mixed>  $data
     */
    public function fillFromPolar(array $data): static
    {
        $status = (string) $data['status'];
        $trialEndsAt = self::time($data['trial_end'] ?? null);
        $currentPeriodEnd = self::time($data['current_period_end'] ?? null);

        // ended_at once revoked, ends_at while a cancellation waits for the
        // end of the period.
        $endsAt = self::time($data['ended_at'] ?? null) ?? self::time($data['ends_at'] ?? null);

        if (! $endsAt && ($data['cancel_at_period_end'] ?? false)) {
            $endsAt = $currentPeriodEnd;
        }

        $this->forceFill([
            'polar_id' => (string) $data['id'],
            'polar_customer_id' => $data['customer_id'] ?? $this->polar_customer_id,
            'polar_product_id' => $data['product_id'] ?? $this->polar_product_id,
            'status' => $status,
            'seats' => $data['seats'] ?? null,
            'trial_ends_at' => $trialEndsAt,
            'had_trial' => (bool) $this->had_trial || $trialEndsAt !== null || ! empty($data['trial_start']),
            'current_period_end' => $currentPeriodEnd,
            'ends_at' => $endsAt,
            // The grace period starts when the payment failed; a missing date
            // is set to now when the row is saved.
            'past_due_at' => $status === self::STATUS_PAST_DUE
                ? (self::time($data['past_due_at'] ?? null) ?? $this->past_due_at)
                : null,
            'polar_modified_at' => $data['modified_at'] ?? $data['created_at'] ?? $this->polar_modified_at,
        ]);

        return $this;
    }

    /**
     * Whether the row already holds this state of the subscription or a newer
     * one (Polar's webhooks can arrive late, and out of order).
     *
     * @param  array<string, mixed>  $data
     */
    public function isNewerThan(array $data): bool
    {
        $stored = self::time($this->polar_modified_at);
        $received = self::time($data['modified_at'] ?? $data['created_at'] ?? null);

        return $stored !== null && $received !== null && $received->lessThanOrEqualTo($stored);
    }

    public function provider(): string
    {
        return 'polar';
    }

    public function providerId(): string
    {
        return $this->polar_id;
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
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_TRIALING], true)
            && ($this->ends_at === null || $this->ends_at->isFuture());
    }

    public function isTrialing(): bool
    {
        return $this->onTrial();
    }

    /** In its trial period (as Cashier names it, for the notifications). */
    public function onTrial(): bool
    {
        return $this->status === self::STATUS_TRIALING && $this->isActive()
            && ($this->trial_ends_at === null || $this->trial_ends_at->isFuture());
    }

    /** Canceled, or scheduled to be at the end of the period. */
    public function isCanceled(): bool
    {
        return $this->ends_at !== null || $this->status === self::STATUS_CANCELED;
    }

    public function isExpired(): bool
    {
        return $this->isCanceled() && ! $this->onGracePeriod();
    }

    /** Canceled, and in effect until the end of the period it was paid for. */
    public function onGracePeriod(): bool
    {
        return $this->ends_at !== null && $this->ends_at->isFuture()
            && $this->status !== self::STATUS_CANCELED;
    }

    public function valid(): bool
    {
        return $this->isActive() || $this->onPaymentGracePeriod();
    }

    /**
     * Cancel at the end of the current period.
     *
     * @throws RequestException
     */
    public function cancel(): static
    {
        return $this->updateOnPolar(['cancel_at_period_end' => true]);
    }

    /**
     * Take back a cancellation that waits for the end of the period.
     *
     * @throws RequestException
     */
    public function stopCancelation(): static
    {
        return $this->updateOnPolar(['cancel_at_period_end' => false]);
    }

    /**
     * End the subscription at once (Polar revokes it).
     *
     * @throws RequestException
     */
    public function cancelNow(): static
    {
        $this->fillFromPolar(app(PolarApi::class)->revokeSubscription($this->polar_id));
        $this->ends_at ??= now();
        $this->save();

        return $this;
    }

    public function markAsCanceled(): static
    {
        $this->forceFill([
            'status' => self::STATUS_CANCELED,
            'ends_at' => $this->ends_at ?? now(),
        ])->save();

        return $this;
    }

    /**
     * Change the subscription on Polar, then store the state it answers with.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws RequestException
     */
    public function updateOnPolar(array $attributes): static
    {
        $this->fillFromPolar(app(PolarApi::class)->updateSubscription($this->polar_id, $attributes))->save();

        return $this;
    }

    protected function startsAt(): Attribute
    {
        return Attribute::get(fn () => $this->created_at);
    }

    protected function cancelAtPeriodEnd(): Attribute
    {
        return Attribute::get(fn () => $this->onGracePeriod());
    }

    protected function cancelAt(): Attribute
    {
        return Attribute::get(fn () => $this->onGracePeriod() ? $this->ends_at : null);
    }

    protected function canceledAt(): Attribute
    {
        return Attribute::get(fn () => $this->status === self::STATUS_CANCELED ? $this->ends_at : null);
    }

    protected function quantity(): Attribute
    {
        return Attribute::get(fn () => $this->seats);
    }

    private static function time(mixed $value): ?CarbonInterface
    {
        return is_string($value) && $value !== '' ? Carbon::parse($value) : null;
    }
}
