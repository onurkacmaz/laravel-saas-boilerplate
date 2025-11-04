<?php

namespace Features\Acl\UpdateRole\Application;

class UpdateRole
{
    public function __construct(
        public string $roleId,
        public ?string $name = null,
        public ?string $slug = null,
        public ?string $description = null,
        public bool $isDefault = false
    ) {}
}
