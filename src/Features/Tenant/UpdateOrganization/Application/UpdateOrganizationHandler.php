<?php

namespace Features\Tenant\UpdateOrganization\Application;

use App\Models\Organization;
use Features\Tenant\Shared\Support\OrganizationSlug;
use Features\Tenant\UpdateOrganization\Events\OrganizationUpdated;

class UpdateOrganizationHandler
{
    public function __invoke(UpdateOrganization $command): Organization
    {
        $organization = Organization::query()->findOrFail($command->organizationId);

        $attributes = array_intersect_key($command->attributes, array_flip(['name', 'description', 'is_active']));

        // Partial update: absent fields keep their current value. The slug only
        // changes when the name actually changes.
        if (isset($attributes['name']) && $attributes['name'] !== $organization->name) {
            $attributes['slug'] = OrganizationSlug::generate($attributes['name'], $organization->id);
        }

        $organization->fill($attributes);

        if ($organization->isDirty()) {
            $organization->save();

            event(new OrganizationUpdated($organization));
        }

        return $organization->fresh();
    }
}
