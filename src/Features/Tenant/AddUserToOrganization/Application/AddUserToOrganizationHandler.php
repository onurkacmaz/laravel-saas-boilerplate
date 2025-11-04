<?php

namespace Features\Tenant\AddUserToOrganization\Application;

use App\Models\Organization;
use App\Models\User;
use Features\Tenant\AddUserToOrganization\Events\UserAddedToOrganization;
use Illuminate\Database\Eloquent\Model;

class AddUserToOrganizationHandler
{
    public function __invoke(AddUserToOrganization $command): Model|User
    {
        $organization = Organization::query()->findOrFail($command->organizationId);
        $user = User::query()->findOrFail($command->userId);

        $organization?->addUser(
            user: $user,
            roleId: $command->roleId);

        event(new UserAddedToOrganization($organization, $user, $command->roleId));

        return $user;
    }
}
