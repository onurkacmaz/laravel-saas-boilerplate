<?php

namespace Features\Tenant\CancelInvitation\Application;

use Dedoc\Scramble\Attributes\Group;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

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

            return response()->noContent();
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $e->getCode());
        }
    }
}
