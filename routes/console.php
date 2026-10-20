<?php

use Illuminate\Support\Facades\Schedule;

// Schedule trial ending notifications
Schedule::command('subscriptions:trial-ending')->daily()->withoutOverlapping()->onOneServer();

// Schedule GDPR data deletions
Schedule::command('gdpr:process-deletions')->daily()->withoutOverlapping()->onOneServer();

// Remove expired Sanctum tokens (kept for 48 hours after expiry for auditing)
Schedule::command('sanctum:prune-expired --hours=48')->daily();
