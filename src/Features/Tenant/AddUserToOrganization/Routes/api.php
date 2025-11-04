<?php

use Features\Tenant\AddUserToOrganization\Application\AddUserToOrganizationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'rate.limit', 'tenant.required'])
    ->post('api/v1/organizations/{organization}/users', AddUserToOrganizationController::class)
    ->name('api.v1.organizations.users.store');
