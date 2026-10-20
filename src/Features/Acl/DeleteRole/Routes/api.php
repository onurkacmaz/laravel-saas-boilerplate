<?php

use Features\Acl\DeleteRole\Application\DeleteRoleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'tenant.required', 'tenant.role:admin', 'rate.limit'])
    ->delete('api/v1/roles/{role}', DeleteRoleController::class)
    ->name('api.v1.roles.destroy');
