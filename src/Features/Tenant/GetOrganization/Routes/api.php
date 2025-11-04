<?php

use Features\Tenant\GetOrganization\Application\GetOrganizationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'rate.limit', 'tenant.required'])
    ->get('api/v1/organizations/{organization}', GetOrganizationController::class)
    ->name('api.v1.organizations.show');
