<?php

namespace Features\Subscription\Infrastructure\Eloquent\Concerns;

use Carbon\CarbonInterface;
use Features\Subscription\Shared\Support\PaymentGracePeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The payment grace period of a paid subscription: when a renewal payment
 * fails, the provider marks the subscription past due and retries. The plan
 * stays in effect for billing.past_due_grace_days from that moment.
 *
 * past_due_at is set when the provider's status becomes past due (whatever
 * writes it: a webhook, a sync) and cleared when it changes again.
 *
 * @property Carbon|null $past_due_at
 * @property Carbon|null $payment_grace_ended_at
 */
trait HasPaymentGracePeriod
{
    /** The column holding the provider's status. */
    abstract protected function paymentStatusColumn(): string;

    public static function bootHasPaymentGracePeriod(): void
    {
        static::saving(function (Model $subscription): void {
            /** @var self $subscription */
            if ($subscription->hasPastDueStatus()) {
                $subscription->past_due_at ??= now();

                return;
            }

            $subscription->past_due_at = null;
            $subscription->payment_grace_ended_at = null;
        });
    }

    public function initializeHasPaymentGracePeriod(): void
    {
        $this->mergeCasts([
            'past_due_at' => 'datetime',
            'payment_grace_ended_at' => 'datetime',
        ]);
    }

    public function pastDueSince(): ?CarbonInterface
    {
        return $this->hasPastDueStatus() ? $this->past_due_at : null;
    }

    public function paymentGraceEndsAt(): ?CarbonInterface
    {
        return $this->pastDueSince()?->copy()->addDays(PaymentGracePeriod::days());
    }

    public function onPaymentGracePeriod(): bool
    {
        return (bool) $this->paymentGraceEndsAt()?->isFuture();
    }

    public function markPaymentGraceEnded(): void
    {
        $this->forceFill(['payment_grace_ended_at' => now()])->save();
    }

    /** Active, trialing, on its grace period, or past due within the payment grace period. */
    public function valid(): bool
    {
        return parent::valid() || $this->onPaymentGracePeriod();
    }

    private function hasPastDueStatus(): bool
    {
        return $this->getAttribute($this->paymentStatusColumn()) === 'past_due';
    }
}
