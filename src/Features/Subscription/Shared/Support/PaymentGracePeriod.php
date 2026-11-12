<?php

namespace Features\Subscription\Shared\Support;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * The days a past due subscription keeps its plan while the provider retries
 * the payment (config billing.past_due_grace_days).
 */
final class PaymentGracePeriod
{
    public static function days(): int
    {
        return max(0, (int) config('billing.past_due_grace_days', 0));
    }

    /** Subscriptions that fell past due after this moment are still on their grace period. */
    public static function cutoff(): CarbonInterface
    {
        return now()->subDays(self::days());
    }

    /**
     * Past due subscriptions still on their grace period.
     *
     * @param  Builder<*>  $query
     */
    public static function ongoing(Builder $query, string $statusColumn): void
    {
        $query->where($statusColumn, 'past_due')->where('past_due_at', '>', self::cutoff());
    }

    /**
     * Past due subscriptions whose grace period is over and was not reported yet.
     *
     * @param  Builder<*>  $query
     */
    public static function endedUnreported(Builder $query, string $statusColumn): void
    {
        $query->where($statusColumn, 'past_due')
            ->where('past_due_at', '<=', self::cutoff())
            ->whereNull('payment_grace_ended_at');
    }
}
