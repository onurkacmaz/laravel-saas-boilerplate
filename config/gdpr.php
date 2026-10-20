<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Deletion Grace Period
    |--------------------------------------------------------------------------
    |
    | Number of days between an account deletion request and the permanent
    | deletion performed by the "gdpr:process-deletions" scheduled command.
    |
    */

    'deletion_grace_days' => (int) env('GDPR_DELETION_GRACE_DAYS', 30),

];
