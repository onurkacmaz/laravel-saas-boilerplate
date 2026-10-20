<?php

use Features\Acl\GetRole\Application\GetRoleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'tenant.required', 'rate.limit'])
    ->get('api/v1/roles/{role}', GetRoleController::class)
    ->name('api.v1.roles.show');
