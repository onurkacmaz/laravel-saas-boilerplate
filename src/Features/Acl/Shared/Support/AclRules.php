<?php

namespace Features\Acl\Shared\Support;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Validation\Rules\Unique;
use Infrastructure\Tenant\TenantContext;

final class AclRules
{
    /**
     * Slugs are unique per organization (soft deleted records excluded).
     */
    public static function uniqueSlug(string $table, ?string $ignoreId = null): Unique
    {
        $rule = (new Unique($table, 'slug'))
            ->where('organization_id', TenantContext::getOrganizationId())
            ->whereNull('deleted_at');

        return $ignoreId ? $rule->ignore($ignoreId) : $rule;
    }

    /**
     * System records (shared by all tenants) are read-only through the tenant API.
     */
    public static function ensureMutable(Role|Permission $record): void
    {
        if ($record->isSystem()) {
            abort(403, sprintf('System %s cannot be modified.', $record instanceof Role ? 'roles' : 'permissions'));
        }
    }

    /**
     * The given user must be a member of the current organization.
     */
    public static function memberOfCurrentOrganization(string $userId): User
    {
        $organization = TenantContext::getOrganization();

        abort_unless($organization !== null, 403, 'Organization context is required.');

        /** @var User $user */
        $user = $organization->users()->findOrFail($userId);

        return $user;
    }
}
