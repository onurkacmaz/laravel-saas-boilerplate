<?php

use Features\Acl\DeletePermission\Application\DeletePermissionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'tenant.required', 'tenant.role:admin', 'rate.limit'])
    ->delete('api/v1/permissions/{permission}', DeletePermissionController::class)
    ->name('api.v1.permissions.destroy');
