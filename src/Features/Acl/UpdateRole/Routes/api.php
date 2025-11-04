<?php

use Features\Acl\UpdateRole\Application\UpdateRoleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'rate.limit', 'tenant.required'])
    ->put('api/v1/roles/{role}', UpdateRoleController::class)
    ->name('api.v1.roles.update');
