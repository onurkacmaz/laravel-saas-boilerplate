<?php

namespace Features\Acl\UpdatePermission\Application;

class UpdatePermission
{
    public function __construct(
        public string $permissionId,
        public ?string $name = null,
        public ?string $slug = null,
        public ?string $description = null
    ) {}
}
