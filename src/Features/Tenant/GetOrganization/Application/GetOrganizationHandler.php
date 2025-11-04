<?php

namespace Features\Tenant\GetOrganization\Application;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;

class GetOrganizationHandler
{
    public function __invoke(GetOrganization $command): Organization|Model
    {
        return Organization::query()
            ->with(['owner', 'users'])
            ->findOrFail($command->organizationId);
    }
}
