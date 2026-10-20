<?php

namespace Features\Tenant\RemoveUserFromOrganization\Events;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserRemovedFromOrganization
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Organization $organization,
        public User $user
    ) {
        //
    }
}
