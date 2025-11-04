<?php

use Features\Tenant\GetInvitations\Application\GetInvitationsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'rate.limit', 'tenant.required'])
    ->get('api/v1/invitations', GetInvitationsController::class)
    ->name('api.v1.invitations.index');
