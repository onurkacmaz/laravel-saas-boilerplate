<?php

use Features\Tenant\SendInvitation\Application\SendInvitationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'rate.limit', 'tenant.required'])
    ->post('api/v1/invitations', SendInvitationController::class)
    ->name('api.v1.invitations.store');
