<?php

namespace Features\Acl\Shared\Support;

use App\Models\Role;
use Infrastructure\Tenant\TenantContext;

/**
 * System-wide roles shared by every organization (stored with a NULL
 * organization_id). They are created by the database seeder and, as a safety
 * net, lazily whenever the application needs one of them.
 */
final class SystemRoles
{
    public const string OWNER = 'owner';

    public const string ADMIN = 'admin';

    public const string MEMBER = 'member';

    /**
     * @return array<string, array{name: string, description: string, is_default: bool}>
     */
    public static function definitions(): array
    {
        return [
            self::OWNER => [
                'name' => 'Owner',
                'description' => 'Full access to the organization, including billing and deletion.',
                'is_default' => false,
            ],
            self::ADMIN => [
                'name' => 'Admin',
                'description' => 'Manage members, roles, settings and integrations.',
                'is_default' => false,
            ],
            self::MEMBER => [
                'name' => 'Member',
                'description' => 'Regular access to the organization.',
                'is_default' => true,
            ],
        ];
    }

    public static function find(string $slug): ?Role
    {
        return Role::query()
            ->withoutOrganization()
            ->whereNull('organization_id')
            ->where('slug', $slug)
            ->first();
    }

    /**
     * Find a system role, creating it from its definition when missing.
     */
    public static function ensure(string $slug): Role
    {
        $role = self::find($slug);

        if ($role) {
            return $role;
        }

        $definition = self::definitions()[$slug] ?? [
            'name' => ucfirst($slug),
            'description' => null,
            'is_default' => false,
        ];

        // System roles have no organization; the tenant context would otherwise
        // stamp the current organization on the new record.
        return TenantContext::runWithoutTenant(fn () => Role::query()->create([
            'organization_id' => null,
            'slug' => $slug,
            ...$definition,
        ]));
    }

    public static function ensureAll(): void
    {
        foreach (array_keys(self::definitions()) as $slug) {
            self::ensure($slug);
        }
    }

    /**
     * Whether the given role grants ownership-level privileges.
     */
    public static function isOwnerRole(?Role $role): bool
    {
        return $role !== null && $role->slug === self::OWNER;
    }
}
