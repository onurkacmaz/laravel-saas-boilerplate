<?php

use Features\Tenant\AcceptInvitation\Application\AcceptInvitationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'rate.limit'])
    ->post('api/v1/invitations/token/{token}/accept', AcceptInvitationController::class)
    ->name('api.v1.invitations.accept');
