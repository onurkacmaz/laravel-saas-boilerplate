<?php

namespace Features\Tenant\RemoveUserFromOrganization\Application;

use App\Models\User;

class RemoveUserFromOrganization
{
    public function __construct(
        public string $organizationId,
        public string $userId,
        public ?User $actor = null,
    ) {}
}
