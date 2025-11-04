<?php

namespace Features\Tenant\RejectInvitation\Application;

class RejectInvitation
{
    public function __construct(
        public string $token
    ) {}
}
