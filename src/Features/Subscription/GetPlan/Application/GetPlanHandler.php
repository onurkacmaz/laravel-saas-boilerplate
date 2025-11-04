<?php

namespace Features\Subscription\GetPlan\Application;

use Features\Subscription\Infrastructure\Eloquent\Models\Plan;

class GetPlanHandler
{
    public function __invoke(GetPlan $command): Plan
    {
        return Plan::query()->where('id', $command->planId)->where('is_active', true)->firstOrFail();
    }
}
