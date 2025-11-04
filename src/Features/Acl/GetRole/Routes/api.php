<?php

use Features\Acl\GetRole\Application\GetRoleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'rate.limit', 'tenant.required'])
    ->get('api/v1/roles/{role}', GetRoleController::class)
    ->name('api.v1.roles.show');
