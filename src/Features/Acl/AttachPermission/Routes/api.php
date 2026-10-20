<?php

use Features\Acl\AttachPermission\Application\AttachPermissionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'tenant.required', 'tenant.role:admin', 'rate.limit'])
    ->post('api/v1/roles/{role}/permissions', AttachPermissionController::class)
    ->name('api.v1.roles.permissions.attach');
