<?php

namespace Features\Acl\AssignToUser\Application;

class AssignToUser
{
    public function __construct(
        public string $userId,
        public string $roleId
    ) {}
}
