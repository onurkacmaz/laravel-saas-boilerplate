<?php

namespace Features\Audit\GetAudit\Application;

use Features\Audit\Infrastructure\Eloquent\Models\AuditLog;
use Features\Audit\Infrastructure\Support\AuditLogger;

class GetAuditHandler
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {}

    public function __invoke(GetAudit $command): AuditLog
    {
        // Scoped to the current organization: other tenants' logs are "not found".
        /** @var AuditLog */
        return $this->auditLogger->query()->findOrFail($command->auditLogId);
    }
}
