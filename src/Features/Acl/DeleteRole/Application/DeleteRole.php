<?php

namespace Features\Acl\DeleteRole\Application;

class DeleteRole
{
    public function __construct(
        public string $roleId
    ) {}
}
