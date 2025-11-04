<?php

namespace Features\Tenant\UpdateUserRoleOnOrganization\Application;

class UpdateUserRoleOnOrganization
{
    public function __construct(
        public string $organizationId,
        public string $userId,
        public string $roleId
    ) {}
}
