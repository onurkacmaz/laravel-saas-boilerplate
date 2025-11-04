<?php

namespace Features\Subscription\GetPlans\Application;

use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Illuminate\Database\Eloquent\Collection;

class GetPlansHandler
{
    public function __invoke(GetPlans $command): Collection
    {
        return Plan::query()
            ->where('is_active', true)
            ->orderBy('price')
            ->get();
    }
}
