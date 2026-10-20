<?php

namespace Infrastructure\Http;

use Illuminate\Http\Request;

final class Pagination
{
    public const int DEFAULT_PER_PAGE = 15;

    public const int MAX_PER_PAGE = 100;

    /**
     * Resolve a safe "per page" value from the request (accepts per_page and perPage).
     */
    public static function perPage(Request $request, int $default = self::DEFAULT_PER_PAGE, int $max = self::MAX_PER_PAGE): int
    {
        $value = $request->input('per_page', $request->input('perPage'));

        if (! is_numeric($value)) {
            return $default;
        }

        return max(1, min($max, (int) $value));
    }

    /**
     * Resolve a safe page number from the request.
     */
    public static function page(Request $request): int
    {
        $value = $request->input('page');

        if (! is_numeric($value)) {
            return 1;
        }

        return max(1, (int) $value);
    }
}
