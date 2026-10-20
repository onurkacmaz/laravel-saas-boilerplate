<?php

namespace Features\Tenant\DeleteOrganization\Events;

use App\Models\Organization;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrganizationDeleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public Organization $organization)
    {
        //
    }
}
