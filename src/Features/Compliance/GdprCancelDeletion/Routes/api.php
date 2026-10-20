<?php

use Features\Compliance\GdprCancelDeletion\Application\GdprCancelDeletionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])
    ->post('api/v1/gdpr/cancel-deletion', GdprCancelDeletionController::class)
    ->name('api.v1.gdpr.cancel-deletion');
