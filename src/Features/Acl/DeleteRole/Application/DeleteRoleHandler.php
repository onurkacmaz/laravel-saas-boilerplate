<?php

namespace Features\Acl\DeleteRole\Application;

use App\Models\Invitation;
use App\Models\OrganizationUser;
use App\Models\Role;
use Features\Acl\DeleteRole\Events\RoleDeleted;
use Features\Acl\Shared\Support\AclRules;
use Features\Tenant\Shared\Enums\InvitationStatus;

class DeleteRoleHandler
{
    public function __invoke(DeleteRole $command): ?bool
    {
        $role = Role::query()->findOrFail($command->roleId);

        AclRules::ensureMutable($role);

        // Deleting a role still held by members would silently strip them of
        // their access; they must be moved to another role first.
        $inUse = OrganizationUser::query()->where('role_id', $role->id)->exists()
            || Invitation::query()->where('role_id', $role->id)->where('status', InvitationStatus::PENDING->value)->exists();

        abort_if($inUse, 422, 'This role is still assigned to members or pending invitations. Reassign them before deleting the role.');

        $deleted = $role->delete();

        if ($deleted) {
            $role->permissions()->detach();
            $role->users()->detach();

            event(new RoleDeleted($role));
        }

        return $deleted;
    }
}
