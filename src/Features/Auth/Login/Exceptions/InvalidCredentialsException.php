<?php

namespace Features\Auth\Login\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class InvalidCredentialsException extends Exception
{
    public function render($request): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => 'Invalid email or password.',
        ], Response::HTTP_UNAUTHORIZED);
    }
}
