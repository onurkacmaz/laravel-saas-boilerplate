<?php

namespace Features\Tenant\UpdateUserRoleOnOrganization\Events;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserRoleUpdated
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
