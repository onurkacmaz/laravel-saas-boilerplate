<?php

namespace Features\Subscription\GetPlan\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Subscription\Shared\Resources\PlanResource;
use Illuminate\Http\Resources\Json\JsonResource;

#[Group('Plans')]
class GetPlanController
{
    /**
     * Get a plan by ID.
     */
    public function __invoke(string $planId, GetPlanHandler $handler): JsonResource
    {
        $plan = $handler(
            command: new GetPlan(
                planId: $planId
            )
        );

        return $plan->toResource(PlanResource::class)->additional([
            'status' => 'success',
            'message' => __('Plan retrieved successfully'),
        ]);
    }
}
