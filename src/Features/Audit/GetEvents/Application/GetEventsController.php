<?php

namespace Features\Audit\GetEvents\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Audit')]
class GetEventsController
{
    /**
     * Get audit log events.
     */
    public function __invoke(GetEventsRequest $request, string $auditLogId, GetEventsHandler $handler): JsonResponse
    {
        $events = $handler(
            command: new GetEvents(
                page: $request->input('page'),
                perPage: $request->input('perPage'),
                search: $request->input('search'),
            )
        );

        return response()->json([
            'data' => $events,
            'status' => 'success',
            'message' => 'Events retrieved successfully.',
        ]);
    }
}
