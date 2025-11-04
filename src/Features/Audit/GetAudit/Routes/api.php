<?php

use Features\Audit\GetAudit\Application\GetAuditController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'rate.limit', 'tenant.required'])
    ->name('api.v1.audit-logs.show')
    ->get('api/v1/audit-logs/{auditLog}', GetAuditController::class);
