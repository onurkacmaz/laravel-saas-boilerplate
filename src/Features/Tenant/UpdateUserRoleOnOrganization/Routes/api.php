<?php

use Features\Tenant\UpdateUserRoleOnOrganization\Application\UpdateUserRoleOnOrganizationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'tenant.required', 'tenant.role:admin', 'rate.limit'])
    ->put('api/v1/organizations/{organization}/users/{user}/role', UpdateUserRoleOnOrganizationController::class)
    ->name('api.v1.organizations.users.role.update');
