<?php

use Features\Audit\GetAudit\Application\GetAuditController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'tenant.required', 'tenant.role:admin', 'rate.limit'])
    ->name('api.v1.audit-logs.show')
    ->get('api/v1/audit-logs/{auditLog}', GetAuditController::class);
