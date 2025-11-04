<?php

use Features\Audit\GetEvents\Application\GetEventsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'rate.limit', 'tenant.required'])
    ->name('api.v1.audit-logs.events.index')
    ->get('api/v1/audit-logs/events', GetEventsController::class);
