<?php

namespace Features\Tenant\AcceptInvitation\Application;

use App\Models\User;

class AcceptInvitation
{
    public function __construct(
        public string $token,
        public User $user
    ) {}
}
