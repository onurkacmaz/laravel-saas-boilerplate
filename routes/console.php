<?php

use Illuminate\Support\Facades\Schedule;

// Schedule trial ending notifications
Schedule::command('subscriptions:trial-ending')->daily()->withoutOverlapping()->onOneServer();

// Report payment grace periods that ran out (no webhook arrives for them)
Schedule::command('subscriptions:end-payment-grace')->hourly()->withoutOverlapping()->onOneServer();

// Schedule GDPR data deletions
Schedule::command('gdpr:process-deletions')->daily()->withoutOverlapping()->onOneServer();

// Remove expired Sanctum tokens (kept for 48 hours after expiry for auditing)
Schedule::command('sanctum:prune-expired --hours=48')->daily();
