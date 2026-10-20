<?php

use Features\Acl\GetPermission\Application\GetPermissionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'tenant.required', 'rate.limit'])
    ->get('api/v1/permissions/{permission}', GetPermissionController::class)
    ->name('api.v1.permissions.show');
