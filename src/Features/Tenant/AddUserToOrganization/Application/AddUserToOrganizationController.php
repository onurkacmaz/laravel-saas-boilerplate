<?php

namespace Features\Tenant\AddUserToOrganization\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Auth\Shared\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Infrastructure\Http\ApiError;
use InvalidArgumentException;

#[Group('Organizations')]
class AddUserToOrganizationController
{
    /**
     * Add a user to an organization.
     */
    public function __invoke(AddUserToOrganizationRequest $request, string $organizationId, AddUserToOrganizationHandler $handler): JsonResponse
    {
        try {
            $user = $handler(
                command: new AddUserToOrganization(
                    organizationId: $organizationId,
                    userId: (string) $request->validated('user_id'),
                    roleId: (string) $request->validated('role_id')
                )
            );
        } catch (InvalidArgumentException $e) {
            return ApiError::fromException($e, 422);
        }

        return $user->toResource(UserResource::class)->additional([
            'status' => 'success',
            'message' => 'User added to organization successfully.',
        ])->response()->setStatusCode(201);
    }
}
