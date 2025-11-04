<?php

use Features\Tenant\CreateOrganization\Application\CreateOrganizationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'rate.limit'])
    ->post('api/v1/organizations', CreateOrganizationController::class)
    ->name('api.v1.organizations.store');
