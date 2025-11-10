<?php

namespace Features\Subscription\Subscribe\Application;

use App\Models\Organization;

/**
 * @property $planId
 */
class Subscribe
{
    public function __construct(
        public string $planId,
        public bool $useTrial,
        public ?int $quantity,
        public ?Organization $organization = null
    ) {}
}
