<?php

namespace Features\Tenant\UpdateOrganization\Application;

use App\Models\Organization;
use Features\Tenant\UpdateOrganization\Events\OrganizationUpdated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UpdateOrganizationHandler
{
    public function __invoke(UpdateOrganization $command): Organization|Model
    {
        $organization = Organization::query()->findOrFail($command->organizationId);

        if (isset($command->name)) {
            $command->slug = $this->generateSlug($command->name);
        }

        $organization?->update([
            'name' => $command->name,
            'slug' => $command->slug,
            'description' => $command->description,
            'is_active' => $command->isActive,
        ]);

        $organization?->fresh();

        event(new OrganizationUpdated($organization));

        return $organization?->fresh();
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
