<?php

namespace Features\Tenant\AcceptInvitation\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Tenant\Shared\Resources\InvitationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use InvalidArgumentException;

#[Group('Invitations')]
class AcceptInvitationController
{
    /**
     * Accept an invitation.
     */
    public function __invoke(Request $request, string $token, AcceptInvitationHandler $handler): JsonResource|JsonResponse
    {
        try {
            $invitation = $handler(
                command: new AcceptInvitation(
                    token: $token,
                    user: $request->user()
                )
            );
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $e->getCode() ?? 400);
        }

        return $invitation->toResource(InvitationResource::class)->additional([
            'status' => 'success',
            'message' => 'Invitation accepted successfully',
        ]);
    }
}
