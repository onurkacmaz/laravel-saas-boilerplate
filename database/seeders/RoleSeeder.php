<?php

namespace Database\Seeders;

use Features\Acl\Shared\Support\SystemRoles;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Create the system-wide roles (owner, admin, member) used by every organization.
     */
    public function run(): void
    {
        SystemRoles::ensureAll();
    }
}
