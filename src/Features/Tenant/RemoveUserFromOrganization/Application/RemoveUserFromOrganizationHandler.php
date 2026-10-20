<?php

namespace Features\Tenant\RemoveUserFromOrganization\Application;

use App\Models\Organization;
use App\Models\User;
use Features\Tenant\RemoveUserFromOrganization\Events\UserRemovedFromOrganization;
use Features\Tenant\Shared\Support\OrganizationRoles;
use InvalidArgumentException;

class RemoveUserFromOrganizationHandler
{
    /**
     * @throws InvalidArgumentException
     */
    public function __invoke(RemoveUserFromOrganization $command): User
    {
        $organization = Organization::query()->findOrFail($command->organizationId);

        /** @var User $user */
        $user = $organization->users()->findOrFail($command->userId);

        if ($organization->isOwner($user)) {
            throw new InvalidArgumentException('The organization owner cannot be removed. Transfer the ownership first.', 422);
        }

        if ($command->actor && ! OrganizationRoles::canManage($organization, $command->actor, $user)) {
            throw new InvalidArgumentException('You do not have permission to remove this member.', 403);
        }

        $organization->removeUser($user);

        event(new UserRemovedFromOrganization($organization, $user));

        return $user;
    }
}
