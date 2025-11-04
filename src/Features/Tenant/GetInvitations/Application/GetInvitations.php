<?php

namespace Features\Tenant\GetInvitations\Application;

class GetInvitations
{
    public function __construct(
        public ?int $page = 1,
        public ?int $perPage = 15
    ) {}
}
