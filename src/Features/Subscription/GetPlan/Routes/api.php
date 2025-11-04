<?php

use Features\Subscription\GetPlan\Application\GetPlanController;
use Illuminate\Support\Facades\Route;

Route::middleware(['rate.limit'])
    ->get('api/v1/plans/{plan}', GetPlanController::class)
    ->name('api.v1.plans.show');
