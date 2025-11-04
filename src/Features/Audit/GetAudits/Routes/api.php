<?php

use Features\Audit\GetAudits\Application\GetAuditsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'rate.limit', 'tenant.required'])
    ->name('api.v1.audit-logs.index')
    ->get('api/v1/audit-logs', GetAuditsController::class);
