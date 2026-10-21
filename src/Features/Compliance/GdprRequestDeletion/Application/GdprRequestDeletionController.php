<?php

namespace Features\Compliance\GdprRequestDeletion\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Compliance')]
class GdprRequestDeletionController
{
    /**
     * Request the deletion of the account.
     *
     * The account is permanently deleted after a grace period (30 days by
     * default) unless the request is cancelled.
     */
    public function __invoke(Request $request, GdprRequestDeletionHandler $handler): JsonResponse
    {
        $deletionDate = $handler(
            command: new GdprRequestDeletion(
                user: $request->user()
            )
        );

        return response()->json([
            'status' => 'success',
            'message' => __('Data deletion requested. Your account will be permanently deleted on :date.', ['date' => $deletionDate->toDateString()]),
            'data' => [
                'deletion_date' => $deletionDate->toDateString(),
            ],
        ]);
    }
}
