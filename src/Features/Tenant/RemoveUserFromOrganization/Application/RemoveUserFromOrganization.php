<?php

namespace Features\Tenant\RemoveUserFromOrganization\Application;

class RemoveUserFromOrganization
{
    public function __construct(
        public string $organizationId,
        public string $userId
    ) {}
}
