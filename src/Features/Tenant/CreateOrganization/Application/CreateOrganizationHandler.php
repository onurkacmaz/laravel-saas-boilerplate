<?php

namespace Features\Tenant\CreateOrganization\Application;

use App\Models\Organization;
use App\Models\User;
use Features\Acl\Shared\Support\SystemRoles;
use Features\Tenant\CreateOrganization\Events\OrganizationCreated;
use Features\Tenant\Shared\Support\OrganizationSlug;
use Illuminate\Support\Facades\DB;

class CreateOrganizationHandler
{
    public function __invoke(CreateOrganization $command): Organization
    {
        $organization = DB::transaction(function () use ($command) {
            $owner = User::query()->findOrFail($command->userId);

            /** @var Organization $organization */
            $organization = Organization::query()->create([
                'name' => $command->name,
                'slug' => OrganizationSlug::generate($command->name),
                'description' => $command->description,
                'owner_id' => $owner->id,
                'is_active' => true,
            ]);

            $organization->addUser($owner, SystemRoles::ensure(SystemRoles::OWNER)->id);

            return $organization;
        });

        event(new OrganizationCreated($organization));

        return $organization->load(['owner', 'users']);
    }
}
