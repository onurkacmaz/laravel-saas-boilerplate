<?php

use Features\Tenant\GetInvitations\Application\GetInvitationsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'tenant.required', 'tenant.role:admin', 'rate.limit'])
    ->get('api/v1/invitations', GetInvitationsController::class)
    ->name('api.v1.invitations.index');
