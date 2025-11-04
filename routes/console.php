<?php

use Illuminate\Support\Facades\Schedule;

// Schedule trial ending notifications
Schedule::command('subscriptions:trial-ending')->daily();

// Schedule GDPR data deletions
Schedule::command('gdpr:process-deletions')->daily();
