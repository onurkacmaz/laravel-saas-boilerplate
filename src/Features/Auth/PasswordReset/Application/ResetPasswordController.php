<?php

namespace Features\Auth\PasswordReset\Application;

use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

#[Group('Authentication')]
class ResetPasswordController
{
    /**
     * Reset the password using the token received by e-mail.
     *
     * Every existing API token of the user is revoked.
     */
    public function __invoke(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'status' => 'error',
                'message' => 'This password reset token is invalid or has expired.',
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Your password has been reset.',
        ]);
    }
}
