<?php

namespace Features\Tenant\RejectInvitation\Application;

use App\Models\User;

class RejectInvitation
{
    public function __construct(
        public string $token,
        public ?User $user = null,
    ) {}
}
