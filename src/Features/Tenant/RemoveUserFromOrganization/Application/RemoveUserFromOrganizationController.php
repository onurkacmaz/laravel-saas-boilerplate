<?php

namespace Features\Tenant\RemoveUserFromOrganization\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Infrastructure\Http\ApiError;
use InvalidArgumentException;

#[Group('Organizations')]
class RemoveUserFromOrganizationController
{
    /**
     * Remove a user from an organization.
     */
    public function __invoke(Request $request, string $organizationId, string $userId, RemoveUserFromOrganizationHandler $handler): Response|JsonResponse
    {
        try {
            $handler(
                command: new RemoveUserFromOrganization(
                    organizationId: $organizationId,
                    userId: $userId,
                    actor: $request->user(),
                )
            );
        } catch (InvalidArgumentException $e) {
            return ApiError::fromException($e, 422);
        }

        return response()->noContent();
    }
}
