<?php

use Features\Compliance\GdprDownload\Application\GdprDownloadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'rate.limit'])
    ->get('api/v1/gdpr/download', GdprDownloadController::class)
    ->name('api.v1.gdpr.download');
