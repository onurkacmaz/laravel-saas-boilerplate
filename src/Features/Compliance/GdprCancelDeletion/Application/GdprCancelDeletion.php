<?php

namespace Features\Compliance\GdprCancelDeletion\Application;

use App\Models\User;

class GdprCancelDeletion
{
    public function __construct(
        public User $user
    ) {}
}
