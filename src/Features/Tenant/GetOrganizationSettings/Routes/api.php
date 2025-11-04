<?php

use Features\Tenant\GetOrganizationSettings\Application\GetOrganizationSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'rate.limit', 'tenant.required'])
    ->get('api/v1/organizations/{organization}/settings', GetOrganizationSettingsController::class)
    ->name('api.v1.organizations.settings.show');
