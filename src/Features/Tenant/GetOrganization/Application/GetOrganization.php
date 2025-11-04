<?php

namespace Features\Tenant\GetOrganization\Application;

class GetOrganization
{
    public function __construct(
        public string $organizationId
    ) {}
}
