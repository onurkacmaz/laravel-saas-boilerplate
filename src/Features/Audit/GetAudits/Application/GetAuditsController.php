<?php

namespace Features\Audit\GetAudits\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Audit')]
class GetAuditsController
{
    /**
     * Get audit logs.
     */
    public function __invoke(GetAuditsRequest $request, string $auditLogId, GetAuditsHandler $handler): JsonResponse
    {
        $auditLogs = $handler(
            command: new GetAudits(
                page: $request->input('page'),
                perPage: $request->input('perPage'),
                event: $request->input('event'),
                userId: $request->input('user_id')
            )
        );

        return response()->json([
            'data' => $auditLogs->items(),
            'meta' => [
                'current_page' => $auditLogs->currentPage(),
                'last_page' => $auditLogs->lastPage(),
                'per_page' => $auditLogs->perPage(),
                'total' => $auditLogs->total(),
            ],
        ]);
    }
}
