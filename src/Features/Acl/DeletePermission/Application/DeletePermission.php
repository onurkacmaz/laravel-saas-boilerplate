<?php

namespace Features\Acl\DeletePermission\Application;

class DeletePermission
{
    public function __construct(
        public string $permissionId
    ) {}
}
