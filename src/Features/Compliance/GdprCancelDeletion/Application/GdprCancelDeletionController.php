<?php

namespace Features\Compliance\GdprCancelDeletion\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Compliance')]
class GdprCancelDeletionController
{
    /**
     * Cancel a user data deletion request.
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
            'message' => 'Data deletion requested. Your account will be permanently deleted in 30 days.',
            'data' => [
                'deletion_date' => now()->addDays(30)->toDateString(),
            ],
        ]);
    }
}
