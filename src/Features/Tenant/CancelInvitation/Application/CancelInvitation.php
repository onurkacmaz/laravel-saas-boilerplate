<?php

namespace Features\Tenant\CancelInvitation\Application;

class CancelInvitation
{
    public function __construct(public string $invitationId) {}
}
