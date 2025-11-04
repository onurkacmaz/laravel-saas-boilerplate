<?php

namespace Features\Tenant\GetOrganizations\Application;

class GetOrganizations
{
    public function __construct(
        public string $userId,
        public ?int $page = 1,
        public ?int $perPage = 10
    ) {}
}
