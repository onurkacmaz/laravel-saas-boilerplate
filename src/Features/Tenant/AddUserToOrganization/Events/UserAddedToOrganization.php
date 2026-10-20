<?php

namespace Features\Tenant\AddUserToOrganization\Events;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserAddedToOrganization
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Organization $organization,
        public User $user,
        public string $roleId
    ) {
        //
    }
}
