<?php

namespace Features\Subscription\GetLimits\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Usage')]
class GetLimitsController
{
    /**
     * Get limits for the current subscription.
     */
    public function __invoke(GetLimitsHandler $handler): JsonResponse
    {
        $limits = $handler(
            command: new GetLimits
        );

        return response()->json([
            'status' => 'success',
            'message' => __('Limits retrieved successfully'),
            'data' => $limits,
        ]);
    }
}
