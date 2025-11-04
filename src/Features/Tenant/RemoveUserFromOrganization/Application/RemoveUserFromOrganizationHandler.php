<?php

namespace Features\Tenant\RemoveUserFromOrganization\Application;

use App\Models\Organization;
use App\Models\User;
use Features\Tenant\RemoveUserFromOrganization\Events\UserRemovedFromOrganization;
use Illuminate\Database\Eloquent\Model;

class RemoveUserFromOrganizationHandler
{
    public function __invoke(RemoveUserFromOrganization $command): Model|User
    {
        $organization = Organization::query()->findOrFail($command->organizationId);
        $user = User::query()->findOrFail($command->userId);

        $organization?->removeUser($user);

        event(new UserRemovedFromOrganization($organization, $user));

        return $user;
    }
}
