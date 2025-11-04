<?php

namespace Features\Compliance\GdprDelete\Application;

use App\Models\User;

class GdprDelete
{
    public function __construct(
        public User $user
    ) {}
}
