<?php

namespace Features\Acl\RemoveFromUser\Application;

class RemoveFromUser
{
    public function __construct(
        public string $userId,
        public string $roleId,
    ) {}
}
