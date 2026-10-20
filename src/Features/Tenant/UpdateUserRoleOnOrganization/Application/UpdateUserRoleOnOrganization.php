<?php

namespace Features\Tenant\UpdateUserRoleOnOrganization\Application;

use App\Models\User;

class UpdateUserRoleOnOrganization
{
    public function __construct(
        public string $organizationId,
        public string $userId,
        public string $roleId,
        public ?User $actor = null,
    ) {}
}
