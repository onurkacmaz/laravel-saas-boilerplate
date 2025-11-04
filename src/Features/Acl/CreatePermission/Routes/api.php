<?php

use Features\Acl\CreatePermission\Application\CreatePermissionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'rate.limit', 'tenant.required'])
    ->post('api/v1/permissions', CreatePermissionController::class)
    ->name('api.v1.permissions.store');
