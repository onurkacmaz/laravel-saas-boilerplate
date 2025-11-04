<?php

namespace Features\Acl\GetPermission\Application;

class GetPermission
{
    public function __construct(
        public string $permissionId
    ) {}
}
