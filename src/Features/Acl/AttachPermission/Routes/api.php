<?php

use Features\Acl\AttachPermission\Application\AttachPermissionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'rate.limit', 'tenant.required'])
    ->post('api/v1/roles/{role}/permissions', AttachPermissionController::class)
    ->name('api.v1.roles.permissions.attach');
