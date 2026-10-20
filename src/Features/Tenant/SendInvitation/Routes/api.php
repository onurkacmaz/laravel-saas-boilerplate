<?php

use Features\Tenant\SendInvitation\Application\SendInvitationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'tenant.required', 'tenant.role:admin', 'rate.limit'])
    ->post('api/v1/invitations', SendInvitationController::class)
    ->name('api.v1.invitations.store');
