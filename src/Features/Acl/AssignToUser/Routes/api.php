<?php

use Features\Acl\AssignToUser\Application\AssignToUserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant.context', 'rate.limit', 'tenant.required'])
    ->post('api/v1/users/{user}/roles', AssignToUserController::class)
    ->name('api.v1.users.roles.assign');
