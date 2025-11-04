<?php

namespace Features\Tenant\SendInvitation\Application;

use App\Models\Organization;
use App\Models\User;

class SendInvitation
{
    public function __construct(
        public Organization $organization,
        public string $email,
        public string $roleId,
        public User $invitedBy
    ) {}
}
