<?php

namespace Features\Audit\GetAudit\Application;

use Features\Audit\Infrastructure\Eloquent\Models\AuditLog;

class GetAuditHandler
{
    public function __invoke(GetAudit $command): AuditLog
    {
        return AuditLog::query()->findOrFail($command->auditLogId)->load(['user', 'organization', 'auditable']);
    }
}
