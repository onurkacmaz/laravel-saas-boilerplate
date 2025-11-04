<?php

use Features\Auth\GetUser\Application\AuthenticatedUserController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->middleware(['api', 'auth:sanctum', 'tenant.context', 'rate.limit'])
    ->name('api.v1.')
    ->get('/user', [AuthenticatedUserController::class, '__invoke'])->name('user');
