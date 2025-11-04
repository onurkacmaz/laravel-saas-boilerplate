<?php

namespace Features\Tenant\GetOrganizationSettings\Application;

class GetOrganizationSettings
{
    public function __construct(
        public string $organizationId
    ) {}
}
