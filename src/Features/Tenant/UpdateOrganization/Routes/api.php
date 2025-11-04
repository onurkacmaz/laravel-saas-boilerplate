<?php

use Features\Tenant\UpdateOrganization\Application\UpdateOrganizationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'rate.limit', 'tenant.required'])
    ->put('api/v1/organizations/{organization}', UpdateOrganizationController::class)
    ->name('api.v1.organizations.update');
