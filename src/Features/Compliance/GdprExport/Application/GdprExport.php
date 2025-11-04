<?php

namespace Features\Compliance\GdprExport\Application;

use App\Models\User;

class GdprExport
{
    public function __construct(
        public User $user
    ) {}
}
