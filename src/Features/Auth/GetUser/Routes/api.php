<?php

use Features\Auth\GetUser\Application\AuthenticatedUserController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->middleware(['auth:sanctum', 'tenant.context', 'api', 'rate.limit'])
    ->name('api.v1.')
    ->get('/user', [AuthenticatedUserController::class, '__invoke'])->name('user');
