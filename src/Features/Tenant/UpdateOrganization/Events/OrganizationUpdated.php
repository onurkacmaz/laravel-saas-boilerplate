<?php

namespace Features\Tenant\UpdateOrganization\Events;

use App\Models\Organization;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrganizationUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Organization $organization)
    {
        //
    }
}
