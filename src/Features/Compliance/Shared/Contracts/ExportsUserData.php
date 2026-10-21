<?php

namespace Features\Compliance\Shared\Contracts;

use App\Models\User;

/**
 * Adds an application's data about a user to the GDPR export.
 *
 * Tag implementations with UserDataExporter::CONTRIBUTORS in a service
 * provider: $this->app->tag([ProjectsExporter::class], UserDataExporter::CONTRIBUTORS);
 */
interface ExportsUserData
{
    /**
     * Key of the section added to the export, e.g. "projects".
     */
    public function section(): string;

    /**
     * The section's content.
     */
    public function export(User $user): array;
}
