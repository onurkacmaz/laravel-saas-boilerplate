<?php

use Features\Acl\UpdatePermission\Application\UpdatePermissionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'rate.limit', 'tenant.required'])
    ->put('api/v1/permissions/{permission}', UpdatePermissionController::class)
    ->name('api.v1.permissions.update');
