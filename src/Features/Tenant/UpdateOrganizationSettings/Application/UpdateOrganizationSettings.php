<?php

namespace Features\Tenant\UpdateOrganizationSettings\Application;

class UpdateOrganizationSettings
{
    public function __construct(
        public string $organizationId,
        public array $data
    ) {}
}
