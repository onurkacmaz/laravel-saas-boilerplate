<?php

use Features\Compliance\GdprDelete\Application\GdprDeleteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'rate.limit'])
    ->get('api/v1/gdpr/delete', GdprDeleteController::class)
    ->name('api.v1.gdpr.delete');
