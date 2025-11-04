<?php

use Features\Acl\GetRoles\Application\GetRolesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'rate.limit', 'tenant.required'])
    ->get('api/v1/roles', GetRolesController::class)
    ->name('api.v1.roles.index');
