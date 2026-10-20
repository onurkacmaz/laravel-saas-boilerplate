<?php

use Features\Acl\UpdateRole\Application\UpdateRoleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'tenant.required', 'tenant.role:admin', 'rate.limit'])
    ->put('api/v1/roles/{role}', UpdateRoleController::class)
    ->name('api.v1.roles.update');
