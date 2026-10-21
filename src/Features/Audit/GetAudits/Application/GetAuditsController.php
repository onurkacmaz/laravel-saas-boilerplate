<?php

namespace Features\Audit\GetAudits\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Audit\Shared\Resources\AuditLogResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Infrastructure\Http\Pagination;

#[Group('Audit')]
class GetAuditsController
{
    /**
     * List the audit logs of the current organization, newest first.
     *
     * Filters: `event`, `user_id`, `from`, `to`; pagination: `page`, `per_page` (max 100).
     */
    public function __invoke(GetAuditsRequest $request, GetAuditsHandler $handler): ResourceCollection
    {
        $auditLogs = $handler(
            command: new GetAudits(
                page: Pagination::page($request),
                perPage: Pagination::perPage($request),
                event: $request->validated('event'),
                userId: $request->validated('user_id'),
                from: $request->validated('from'),
                to: $request->validated('to'),
            )
        );

        return AuditLogResource::collection($auditLogs)->additional([
            'status' => 'success',
            'message' => __('Audit logs retrieved successfully.'),
        ]);
    }
}
