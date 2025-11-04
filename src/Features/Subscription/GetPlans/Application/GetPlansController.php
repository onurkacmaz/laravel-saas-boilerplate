<?php

namespace Features\Subscription\GetPlans\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Subscription\Shared\Resources\PlanResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Throwable;

#[Group('Plans')]
class GetPlansController
{
    /**
     * Get all plans.
     *
     * @throws Throwable
     */
    public function __invoke(GetPlansHandler $handler): ResourceCollection
    {
        $plans = $handler(
            command: new GetPlans
        );

        return $plans->toResourceCollection(PlanResource::class)->additional([
            'status' => 'success',
            'message' => 'Plans retrieved successfully.',
        ]);
    }
}
