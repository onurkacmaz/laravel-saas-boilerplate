<?php

namespace Features\Acl\CreateRole\Events;

use App\Models\Role;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoleCreated
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Role $role
    ) {
        //
    }
}
