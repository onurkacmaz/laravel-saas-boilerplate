<?php

namespace Features\Audit\GetAudit\Application;

class GetAudit
{
    public function __construct(public string $auditLogId) {}
}
