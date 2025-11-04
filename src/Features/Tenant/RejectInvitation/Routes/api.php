<?php

use Features\Tenant\RejectInvitation\Application\RejectInvitationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'rate.limit'])
    ->post('api/v1/invitations/token/{token}/reject', RejectInvitationController::class)
    ->name('api.v1.invitations.reject');
