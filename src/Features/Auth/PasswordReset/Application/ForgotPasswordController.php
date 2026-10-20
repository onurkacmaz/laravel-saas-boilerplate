<?php

namespace Features\Auth\PasswordReset\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

#[Group('Authentication')]
class ForgotPasswordController
{
    /**
     * Request a password reset link.
     *
     * The response is identical whether or not the e-mail address is
     * registered, so this endpoint cannot be used to enumerate accounts.
     */
    public function __invoke(ForgotPasswordRequest $request): JsonResponse
    {
        Password::broker()->sendResetLink(['email' => $request->validated('email')]);

        return response()->json([
            'status' => 'success',
            'message' => 'If an account exists for this e-mail address, a password reset link has been sent.',
        ]);
    }
}
