<?php

namespace Features\Tenant\Shared\Support;

use App\Models\Organization;
use Illuminate\Support\Str;

final class OrganizationSlug
{
    /**
     * Generate a slug that is unique among organizations (soft deleted ones
     * included, so that restoring an organization can never collide).
     */
    public static function generate(string $name, ?string $ignoreId = null): string
    {
        $base = Str::limit(Str::slug($name), 200, '');

        // Names made only of symbols/emoji produce an empty slug.
        if ($base === '') {
            $base = 'organization';
        }

        $slug = $base;

        for ($attempt = 0; self::exists($slug, $ignoreId); $attempt++) {
            $slug = $base.'-'.Str::lower(Str::random($attempt < 5 ? 4 : 8));
        }

        return $slug;
    }

    private static function exists(string $slug, ?string $ignoreId): bool
    {
        return Organization::query()
            ->withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }
}
