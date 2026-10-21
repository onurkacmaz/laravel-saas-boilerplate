<?php

namespace Features\Tenant\GetInvitations\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Tenant\Shared\Enums\InvitationStatus;
use Features\Tenant\Shared\Resources\InvitationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Infrastructure\Http\Pagination;
use Throwable;

#[Group('Invitations')]
class GetInvitationsController
{
    /**
     * Get all invitations for the current organization.
     *
     * Supports `page`, `per_page` (max 100) and `status` query parameters.
     *
     * @throws Throwable
     */
    public function __invoke(Request $request, GetInvitationsHandler $handler): ResourceCollection
    {
        $status = InvitationStatus::tryFrom((string) $request->query('status'))?->value;

        return $handler(
            command: new GetInvitations(
                page: Pagination::page($request),
                perPage: Pagination::perPage($request),
                status: $status,
            )
        )->toResourceCollection(InvitationResource::class)->additional([
            'status' => 'success',
            'message' => __('Invitations retrieved successfully.'),
        ]);
    }
}
