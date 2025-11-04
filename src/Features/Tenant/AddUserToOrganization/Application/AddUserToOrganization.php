<?php

namespace Features\Tenant\AddUserToOrganization\Application;

class AddUserToOrganization
{
    public function __construct(
        public string $organizationId,
        public string $userId,
        public string $roleId,
    ) {}
}
