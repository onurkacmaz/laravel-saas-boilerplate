<?php

namespace Features\Tenant\CreateOrganization\Application;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Features\Tenant\CreateOrganization\Events\OrganizationCreated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreateOrganizationHandler
{
    public function __invoke(CreateOrganization $command): Model|Organization
    {
        $owner = User::query()->findOrFail($command->userId);

        $organization = Organization::query()->create(
            attributes: [
                'name' => $command->name,
                'slug' => $this->generateSlug($command->name),
                'description' => $command->description,
                'owner_id' => $command->userId,
                'is_active' => true,
            ]
        );

        $ownerRole = Role::query()
            ->where('slug', 'owner')
            ->firstOrFail();

        $organization->addUser($owner, $ownerRole->id);

        event(new OrganizationCreated($organization));

        return $organization->load(['owner', 'users']);
    }

    private function generateSlug(string $name): string
    {
        $slug = Str::slug($name);

        if (Organization::query()->where('slug', $slug)->exists()) {
            return $this->generateSlug($name.'-'.Str::random(4));
        }

        return $slug;
    }
}
