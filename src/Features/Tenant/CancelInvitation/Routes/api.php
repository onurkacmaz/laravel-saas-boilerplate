<?php

use Features\Tenant\CancelInvitation\Application\CancelInvitationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'tenant.required', 'tenant.role:admin', 'rate.limit'])
    ->delete('api/v1/invitations/{invitation}', CancelInvitationController::class)
    ->name('api.v1.invitations.cancel');
