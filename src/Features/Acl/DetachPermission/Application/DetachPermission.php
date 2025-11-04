<?php

namespace Features\Acl\DetachPermission\Application;

class DetachPermission
{
    public function __construct(
        public string $roleId,
        public string $permissionId
    ) {}
}
