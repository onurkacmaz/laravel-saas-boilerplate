<?php

namespace Features\Acl\AttachPermission\Application;

class AttachPermission
{
    public function __construct(
        public string $roleId,
        public string $permissionId
    ) {}
}
