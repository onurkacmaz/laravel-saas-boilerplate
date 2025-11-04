<?php

namespace Features\Subscription\Subscribe\Application;

/**
 * @property $planId
 */
class Subscribe
{
    public function __construct(
        public string $planId,
        public bool $useTrial,
        public ?int $quantity
    ) {}
}
