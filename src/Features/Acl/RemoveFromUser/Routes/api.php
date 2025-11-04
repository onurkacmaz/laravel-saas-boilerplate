<?php

use Features\Acl\RemoveFromUser\Application\RemoveFromUserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'rate.limit', 'tenant.required'])
    ->delete('api/v1/users/{user}/roles/{role}', RemoveFromUserController::class)
    ->name('api.v1.users.roles.remove');
