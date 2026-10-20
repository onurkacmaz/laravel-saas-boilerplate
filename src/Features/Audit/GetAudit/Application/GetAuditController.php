<?php

namespace Features\Audit\GetAudit\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Audit\Shared\Resources\AuditLogResource;
use Illuminate\Http\Resources\Json\JsonResource;

#[Group('Audit')]
class GetAuditController
{
    /**
     * Get an audit log entry of the current organization.
     */
    public function __invoke(string $auditLogId, GetAuditHandler $handler): JsonResource
    {
        $auditLog = $handler(
            command: new GetAudit(
                auditLogId: $auditLogId
            )
        );

        return (new AuditLogResource($auditLog))->additional([
            'status' => 'success',
            'message' => 'Audit log retrieved successfully.',
        ]);
    }
}
