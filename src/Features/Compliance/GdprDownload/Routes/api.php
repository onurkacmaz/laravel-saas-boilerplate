<?php

use Features\Compliance\GdprDownload\Application\GdprDownloadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:6,1'])
    ->get('api/v1/gdpr/download', GdprDownloadController::class)
    ->name('api.v1.gdpr.download');
