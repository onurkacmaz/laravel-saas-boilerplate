<?php

namespace Features\Compliance\GdprRequestDeletion\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Compliance')]
class GdprRequestDeletionController
{
    /**
     * Cancel a user data deletion request.
     */
    public function __invoke(Request $request, GdprRequestDeletionHandler $handler): JsonResponse
    {
        $handler(
            command: new GdprRequestDeletion(
                user: $request->user()
            )
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Data deletion request cancelled successfully',
        ]);
    }
}
