<?php

namespace Features\Tenant\GetInvitationByToken\Application;

class GetInvitationByToken
{
    public function __construct(public string $token) {}
}
