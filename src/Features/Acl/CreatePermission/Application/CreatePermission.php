<?php

namespace Features\Acl\CreatePermission\Application;

class CreatePermission
{
    public function __construct(
        public string $name,
        public ?string $slug = null,
        public ?string $description = null,
    ) {}
}
