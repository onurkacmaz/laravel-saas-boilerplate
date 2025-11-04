<?php

namespace Features\Audit\GetAudit\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Audit')]
class GetAuditController
{
    /**
     * Get an audit log.
     */
    public function __invoke(string $auditLogId, GetAuditHandler $handler): JsonResponse
    {
        $auditLog = $handler(
            command: new GetAudit(
                auditLogId: $auditLogId
            )
        );

        return response()->json([
            'data' => [
                'id' => $auditLog->id,
                'event' => $auditLog->event,
                'description' => $auditLog->description,
                'user' => $auditLog->user ? [
                    'id' => $auditLog->user->id,
                    'name' => $auditLog->user->name,
                    'email' => $auditLog->user->email,
                ] : null,
                'organization' => $auditLog->organization ? [
                    'id' => $auditLog->organization->id,
                    'name' => $auditLog->organization->name,
                ] : null,
                'auditable' => $auditLog->auditable ? [
                    'type' => class_basename($auditLog->auditable_type),
                    'id' => $auditLog->auditable_id,
                ] : null,
                'changes' => $auditLog->changes,
                'metadata' => $auditLog->metadata,
                'ip_address' => $auditLog->ip_address,
                'user_agent' => $auditLog->user_agent,
                'created_at' => $auditLog->created_at->toIso8601String(),
            ],
            'status' => 'success',
            'message' => 'Audit log retrieved successfully.',
        ]);
    }
}
