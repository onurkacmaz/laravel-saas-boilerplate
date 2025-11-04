<?php

use Features\Subscription\GetPlans\Application\GetPlansController;
use Illuminate\Support\Facades\Route;

Route::middleware(['rate.limit'])
    ->get('api/v1/plans', GetPlansController::class)
    ->name('api.v1.plans.index');
