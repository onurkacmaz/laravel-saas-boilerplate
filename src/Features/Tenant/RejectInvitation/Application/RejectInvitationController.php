<?php

namespace Features\Tenant\RejectInvitation\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Tenant\Shared\Resources\InvitationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use InvalidArgumentException;

#[Group('Invitations')]
class RejectInvitationController
{
    /**
     * Reject an invitation.
     */
    public function __invoke(Request $request, string $token, RejectInvitationHandler $handler): JsonResponse|JsonResource
    {
        try {
            $invitation = $handler(
                command: new RejectInvitation(
                    token: $token,
                )
            );
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getCode());
        }

        return $invitation->toResource(InvitationResource::class)->additional([
            'status' => 'success',
            'message' => 'Invitation rejected successfully',
        ]);
    }
}
