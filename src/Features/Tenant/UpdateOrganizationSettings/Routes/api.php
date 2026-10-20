<?php

use Features\Tenant\UpdateOrganizationSettings\Application\UpdateOrganizationSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'tenant.required', 'tenant.role:admin', 'rate.limit'])
    ->put('api/v1/organizations/{organization}/settings', UpdateOrganizationSettingsController::class)
    ->name('api.v1.organizations.settings.update');
