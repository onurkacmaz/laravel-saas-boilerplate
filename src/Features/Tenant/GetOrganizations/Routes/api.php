<?php

use Features\Tenant\GetOrganizations\Application\GetOrganizationsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'rate.limit'])
    ->get('api/v1/organizations', GetOrganizationsController::class)
    ->name('api.v1.organizations.index');
