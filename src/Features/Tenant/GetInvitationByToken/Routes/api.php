<?php

use Features\Tenant\GetInvitationByToken\Application\GetInvitationByTokenController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'rate.limit'])
    ->get('api/v1/invitations/token/{token}', GetInvitationByTokenController::class)
    ->name('api.v1.invitations.show-by-token');
