<?php

namespace Features\Tenant\UpdateUserRoleOnOrganization\Application;

use App\Models\Organization;
use App\Models\User;
use Features\Tenant\UpdateUserRoleOnOrganization\Events\UserRoleUpdated;
use Illuminate\Database\Eloquent\Model;

class UpdateUserRoleOnOrganizationHandler
{
    public function __invoke(UpdateUserRoleOnOrganization $command): User|Model
    {
        $organization = Organization::query()->findOrFail($command->organizationId);
        $user = $organization?->users()->findOrFail($command->userId);

        $organization?->updateUserRole(
            user: $user,
            roleId: $command->roleId,
        );

        event(new UserRoleUpdated($organization, $user, $command->roleId));

        return $user?->fresh();
    }
}
