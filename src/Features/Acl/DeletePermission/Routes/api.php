<?php

use Features\Acl\DeletePermission\Application\DeletePermissionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'rate.limit', 'tenant.required'])
    ->delete('api/v1/permissions/{permission}', DeletePermissionController::class)
    ->name('api.v1.permissions.destroy');
