<?php

namespace Features\Tenant\UpdateOrganization\Application;

class UpdateOrganization
{
    public function __construct(
        public string $organizationId,
        public ?string $name = null,
        public ?string $slug = null,
        public ?string $description = null,
        public bool $isActive = false
    ) {}
}
