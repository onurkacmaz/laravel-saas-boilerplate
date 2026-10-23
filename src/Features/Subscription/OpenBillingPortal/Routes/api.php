<?php

use Features\Subscription\OpenBillingPortal\Application\OpenBillingPortalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'tenant.required', 'tenant.role:admin', 'rate.limit'])
    ->post('api/v1/subscriptions/portal', OpenBillingPortalController::class)
    ->name('api.v1.subscriptions.portal');
