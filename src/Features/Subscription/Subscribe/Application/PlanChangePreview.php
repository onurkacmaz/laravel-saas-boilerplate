<?php

namespace Features\Subscription\Subscribe\Application;

/**
 * What moving a paid subscription to another paid plan costs right away: the
 * provider charges the payment method on file without a checkout page.
 */
class PlanChangePreview
{
    public function __construct(
        /** Whether the change is billed at once; false while trialing, or when the difference goes on the next invoice. */
        public bool $chargedNow,
        /** Charged at once, taxes included and the unused time credited, in the currency's smallest unit; null when the provider cannot tell. */
        public ?int $amountDue,
        /** Lowercase ISO code, e.g. "usd"; null when the provider cannot tell. */
        public ?string $currency,
    ) {}

    public function toArray(): array
    {
        return [
            'charged_now' => $this->chargedNow,
            'amount_due' => $this->amountDue,
            'currency' => $this->currency,
        ];
    }
}
