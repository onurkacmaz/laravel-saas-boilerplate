<?php

use Features\Acl\GetPermission\Application\GetPermissionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'rate.limit', 'tenant.required'])
    ->get('api/v1/permissions/{permission}', GetPermissionController::class)
    ->name('api.v1.permissions.show');
