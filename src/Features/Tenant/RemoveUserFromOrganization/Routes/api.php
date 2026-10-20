<?php

use Features\Tenant\RemoveUserFromOrganization\Application\RemoveUserFromOrganizationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'tenant.required', 'tenant.role:admin', 'rate.limit'])
    ->delete('api/v1/organizations/{organization}/users/{user}', RemoveUserFromOrganizationController::class)
    ->name('api.v1.organizations.users.destroy');
