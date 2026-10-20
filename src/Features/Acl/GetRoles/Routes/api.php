<?php

use Features\Acl\GetRoles\Application\GetRolesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'tenant.required', 'rate.limit'])
    ->get('api/v1/roles', GetRolesController::class)
    ->name('api.v1.roles.index');
