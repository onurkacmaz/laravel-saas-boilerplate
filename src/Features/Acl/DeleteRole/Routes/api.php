<?php

use Features\Acl\DeleteRole\Application\DeleteRoleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'rate.limit', 'tenant.required'])
    ->delete('api/v1/roles/{role}', DeleteRoleController::class)
    ->name('api.v1.roles.destroy');
