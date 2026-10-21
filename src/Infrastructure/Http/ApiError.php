<?php

namespace Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Throwable;

final class ApiError
{
    /**
     * Build a JSON error response. The message is translated to the request's
     * locale when a translation exists for it.
     */
    public static function make(string $message, int $status = 400, array $extra = []): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => __($message),
            ...$extra,
        ], $status);
    }

    /**
     * Build a JSON error response from an exception, using its code as the HTTP
     * status when it is a valid client/server error code.
     */
    public static function fromException(Throwable $exception, int $fallbackStatus = 400): JsonResponse
    {
        $code = (int) $exception->getCode();
        $status = ($code >= 400 && $code < 600) ? $code : $fallbackStatus;

        return self::make($exception->getMessage(), $status);
    }
}
