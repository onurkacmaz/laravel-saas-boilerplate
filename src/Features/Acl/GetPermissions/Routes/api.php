<?php

use Features\Acl\GetPermissions\Application\GetPermissionsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'rate.limit', 'tenant.required'])
    ->get('api/v1/permissions', GetPermissionsController::class)
    ->name('api.v1.permissions.index');
