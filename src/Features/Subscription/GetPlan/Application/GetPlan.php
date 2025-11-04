<?php

namespace Features\Subscription\GetPlan\Application;

class GetPlan
{
    public function __construct(
        public string $planId
    ) {}
}
