<?php

namespace Features\Subscription\Subscribe\Application;

class CheckoutSession
{
    public function __construct(
        public string $id,
        public string $url,
        public int $expiresAt,
        public ?string $customerId = null
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
        ];
    }
}
