<?php

namespace Features\Tenant\GetOrganizations\Application;

use App\Models\Organization;
use Illuminate\Pagination\LengthAwarePaginator;

class GetOrganizationsHandler
{
    public function __invoke(GetOrganizations $command): LengthAwarePaginator
    {
        return Organization::query()
            ->with(['owner', 'users'])
            ->whereHas('users', fn ($query) => $query->where('user_id', $command->userId))
            ->paginate(
                perPage: $command->perPage,
                page: $command->page,
            );
    }
}
