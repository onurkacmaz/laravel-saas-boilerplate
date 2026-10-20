<?php

namespace Features\Subscription\Shared\Events;

use App\Models\Organization;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentSucceeded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Organization $organization,
        public array $paymentData
    ) {}
}
