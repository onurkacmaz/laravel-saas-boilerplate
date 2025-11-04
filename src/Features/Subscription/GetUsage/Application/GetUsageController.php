<?php

namespace Features\Subscription\GetUsage\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Usage')]
class GetUsageController
{
    /**
     * Get usage for the current subscription.
     */
    public function __invoke(GetUsageHandler $handler): JsonResponse
    {
        $usage = $handler(
            command: new GetUsage
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Usage retrieved successfully',
            'data' => $usage,
        ]);
    }
}
