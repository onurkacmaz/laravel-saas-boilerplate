<?php

namespace Features\Audit\GetEvents\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Audit')]
class GetEventsController
{
    /**
     * List the distinct audit event names recorded for the current organization.
     */
    public function __invoke(GetEventsRequest $request, GetEventsHandler $handler): JsonResponse
    {
        $search = $request->query('search');

        $events = $handler(
            command: new GetEvents(
                search: is_string($search) ? $search : null,
            )
        );

        return response()->json([
            'data' => $events,
            'status' => 'success',
            'message' => 'Events retrieved successfully.',
        ]);
    }
}
