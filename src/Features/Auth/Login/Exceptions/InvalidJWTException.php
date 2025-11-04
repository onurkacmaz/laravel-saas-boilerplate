<?php

namespace Features\Auth\Login\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class InvalidJWTException extends Exception
{
    public function render($request): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => 'Invalid or expired JWT token.',
        ], Response::HTTP_UNAUTHORIZED);
    }
}
