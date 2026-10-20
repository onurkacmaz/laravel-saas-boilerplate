<?php

namespace Features\Tenant\CancelInvitation\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Infrastructure\Http\ApiError;
use InvalidArgumentException;

#[Group('Invitations')]
class CancelInvitationController
{
    /**
     * Cancel an invitation.
     */
    public function __invoke(string $invitationId, CancelInvitationHandler $handler): Response|JsonResponse
    {
        try {
            $handler(
                command: new CancelInvitation(
                    invitationId: $invitationId
                )
            );
        } catch (InvalidArgumentException $e) {
            return ApiError::fromException($e);
        }

        return response()->noContent();
    }
}
