<?php

namespace Features\Tenant\CreateOrganization\Application;

class CreateOrganization
{
    public function __construct(
        public string $userId,
        public string $name,
        public ?string $description = null,
    ) {}
}
