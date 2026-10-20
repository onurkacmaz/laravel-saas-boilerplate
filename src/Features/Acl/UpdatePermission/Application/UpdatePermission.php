<?php

namespace Features\Acl\UpdatePermission\Application;

class UpdatePermission
{
    /**
     * @param  array{name?: string, slug?: string|null, description?: string|null}  $attributes  Only the provided keys are updated.
     */
    public function __construct(
        public string $permissionId,
        public array $attributes = [],
    ) {}
}
