<?php

namespace Features\Subscription\Subscribe\Application;

class CheckoutSession
{
    /** Pay on the Stripe Checkout page at `url`. */
    public const string ACTION_CHECKOUT = 'checkout';

    /** The plan is active already; `url` is the success page. */
    public const string ACTION_ACTIVATED = 'activated';

    /** The paid plan stays until the end of the period, then the free plan takes over. */
    public const string ACTION_SCHEDULED = 'scheduled';

    public function __construct(
        public string $id,
        public string $url,
        public int $expiresAt,
        public ?string $customerId = null,
        public string $action = self::ACTION_CHECKOUT,
    ) {}

    /**
     * Convert session to array representation.
     */
    public function toArray(): array
    {
        return [
            'session_id' => $this->id,
            'checkout_url' => $this->url,
            'expires_at' => $this->expiresAt,
            'customer_id' => $this->customerId,
            'action' => $this->action,
        ];
    }
}
