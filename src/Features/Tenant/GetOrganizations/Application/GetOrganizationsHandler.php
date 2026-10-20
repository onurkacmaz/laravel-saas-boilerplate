<?php

namespace Features\Tenant\GetOrganizations\Application;

use App\Models\Organization;
use Illuminate\Pagination\LengthAwarePaginator;

class GetOrganizationsHandler
{
    public function __invoke(GetOrganizations $command): LengthAwarePaginator
    {
        return Organization::query()
            ->with(['owner'])
            ->withCount('users')
            ->where(function ($query) use ($command) {
                $query->whereHas('users', fn ($query) => $query->where('user_id', $command->userId))
                    ->orWhere('owner_id', $command->userId);
            })
            ->orderBy('name')
            ->paginate(
                perPage: $command->perPage,
                page: $command->page,
            );
    }
}
