<?php

use Features\Acl\DetachPermission\Application\DetachPermissionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'tenant.required', 'tenant.role:admin', 'rate.limit'])
    ->delete('api/v1/roles/{role}/permissions/{permission}', DetachPermissionController::class)
    ->name('api.v1.roles.permissions.detach');
