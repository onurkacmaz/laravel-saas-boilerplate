<?php

use Features\Tenant\GetOrganization\Application\GetOrganizationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'tenant.required', 'rate.limit'])
    ->get('api/v1/organizations/{organization}', GetOrganizationController::class)
    ->name('api.v1.organizations.show');
