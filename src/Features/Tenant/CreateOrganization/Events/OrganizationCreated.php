<?php

namespace Features\Tenant\CreateOrganization\Events;

use App\Models\Organization;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrganizationCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Organization $organization)
    {
        //
    }
}
