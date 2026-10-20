<?php

namespace Features\Tenant\UpdateOrganization\Application;

readonly class UpdateOrganization
{
    /**
     * @param  array{name?: string, description?: string|null, is_active?: bool}  $attributes  Only the provided keys are updated.
     */
    public function __construct(
        public string $organizationId,
        public array $attributes = [],
    ) {}
}
