<?php

use Features\Compliance\GdprRequestDeletion\Application\GdprRequestDeletionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'rate.limit'])
    ->get('api/v1/gdpr/request-deletion', GdprRequestDeletionController::class)
    ->name('api.v1.gdpr.request-deletion');
