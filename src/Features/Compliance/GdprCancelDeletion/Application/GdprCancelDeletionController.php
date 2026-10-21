<?php

namespace Features\Compliance\GdprCancelDeletion\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Compliance')]
class GdprCancelDeletionController
{
    /**
     * Cancel a pending account deletion request.
     */
    public function __invoke(Request $request, GdprCancelDeletionHandler $handler): JsonResponse
    {
        $handler(
            command: new GdprCancelDeletion(
                user: $request->user()
            )
        );

        return response()->json([
            'status' => 'success',
            'message' => __('Data deletion request cancelled successfully'),
        ]);
    }
}
