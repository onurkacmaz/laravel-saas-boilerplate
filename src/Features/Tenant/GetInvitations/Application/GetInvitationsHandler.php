<?php

namespace Features\Tenant\GetInvitations\Application;

use App\Models\Invitation;
use Illuminate\Pagination\LengthAwarePaginator;
use Infrastructure\Tenant\TenantContext;

class GetInvitationsHandler
{
    public function __invoke(GetInvitations $command): LengthAwarePaginator
    {
        $organization = TenantContext::getOrganization();

        return Invitation::query()
            ->where('organization_id', $organization?->id)
            ->with(['role', 'invitedBy'])
            ->latest()
            ->paginate(
                perPage: $command->perPage,
                page: $command->page
            );
    }
}
