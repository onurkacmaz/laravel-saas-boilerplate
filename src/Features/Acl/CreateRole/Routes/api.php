<?php

use Features\Acl\CreateRole\Application\CreateRoleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'rate.limit', 'tenant.required'])
    ->post('api/v1/roles', CreateRoleController::class)
    ->name('api.v1.roles.store');
