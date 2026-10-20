<?php

use Features\Acl\CreateRole\Application\CreateRoleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'tenant.required', 'tenant.role:admin', 'rate.limit'])
    ->post('api/v1/roles', CreateRoleController::class)
    ->name('api.v1.roles.store');
