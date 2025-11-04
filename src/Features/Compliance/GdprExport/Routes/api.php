<?php

use Features\Compliance\GdprExport\Application\GdprExportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'rate.limit'])
    ->get('api/v1/gdpr/export', GdprExportController::class)
    ->name('api.v1.gdpr.export');
