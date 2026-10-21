<?php

namespace Features\Tenant\UpdateUserRoleOnOrganization\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Auth\Shared\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Infrastructure\Http\ApiError;
use InvalidArgumentException;

#[Group('Organizations')]
class UpdateUserRoleOnOrganizationController
{
    /**
     * Update a user's role on an organization.
     */
    public function __invoke(UpdateUserRoleOnOrganizationRequest $request, string $organizationId, string $userId, UpdateUserRoleOnOrganizationHandler $handler): JsonResource|JsonResponse
    {
        try {
            $user = $handler(
                command: new UpdateUserRoleOnOrganization(
                    organizationId: $organizationId,
                    userId: $userId,
                    roleId: (string) $request->validated('role_id'),
                    actor: $request->user(),
                )
            );
        } catch (InvalidArgumentException $e) {
            return ApiError::fromException($e, 422);
        }

        return $user->toResource(UserResource::class)->additional([
            'status' => 'success',
            'message' => __('User role updated successfully'),
        ]);
    }
}
