<?php

namespace Features\Tenant\UpdateUserRoleOnOrganization\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Auth\Shared\Resources\UserResource;
use Illuminate\Http\Resources\Json\JsonResource;

#[Group('Organizations')]
class UpdateUserRoleOnOrganizationController
{
    /**
     * Update a user's role on an organization.'
     */
    public function __invoke(UpdateUserRoleOnOrganizationRequest $request, string $organizationId, string $userId, UpdateUserRoleOnOrganizationHandler $handler): JsonResource
    {
        $user = $handler(
            command: new UpdateUserRoleOnOrganization(
                organizationId: $organizationId,
                userId: $userId,
                roleId: $request->input('role_id')
            )
        );

        return $user->toResource(UserResource::class)->additional([
            'status' => 'success',
            'message' => 'User role updated successfully',
        ]);
    }
}
