<?php

namespace Features\Compliance\GdprRequestDeletion\Application;

use App\Models\User;

class GdprRequestDeletion
{
    public function __construct(
        public User $user
    ) {}
}
