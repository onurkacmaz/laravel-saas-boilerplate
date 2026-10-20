<?php

use Features\Tenant\DeleteOrganization\Application\DeleteOrganizationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'tenant.required', 'tenant.role:owner', 'rate.limit'])
    ->delete('api/v1/organizations/{organization}', DeleteOrganizationController::class)
    ->name('api.v1.organizations.destroy');
