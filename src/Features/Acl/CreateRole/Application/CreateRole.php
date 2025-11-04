<?php

namespace Features\Acl\CreateRole\Application;

class CreateRole
{
    public function __construct(
        public string $name,
        public ?string $slug = null,
        public ?string $description = null,
        public bool $isDefault = false
    ) {}
}
