<?php

namespace Features\Acl\UpdateRole\Application;

class UpdateRole
{
    /**
     * @param  array{name?: string, slug?: string|null, description?: string|null, is_default?: bool}  $attributes  Only the provided keys are updated.
     */
    public function __construct(
        public string $roleId,
        public array $attributes = [],
    ) {}
}
