<?php

namespace Features\Tenant\DeleteOrganization\Application;

class DeleteOrganization
{
    public function __construct(
        public string $organizationId
    ) {}
}
