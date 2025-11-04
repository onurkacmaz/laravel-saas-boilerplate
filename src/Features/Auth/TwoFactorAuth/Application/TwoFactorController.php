<?php

namespace Features\Auth\TwoFactorAuth\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Random\RandomException;
use Symfony\Component\HttpFoundation\Response;

#[Group('Two Factor Authentication')]
class TwoFactorController
{
    /**
     * Enable 2FA for the current user.
     *
     * @throws RandomException
     */
    public function enable(EnableTwoFactorRequest $request, EnableTwoFactorHandler $handler): JsonResponse
    {
        $data = $handler(
            command: new EnableTwoFactor(
                user: auth()->user()
            )
        );

        return response()->json([
            'status' => 'success',
            'message' => '2FA enabled for this user. Please check your email for the OTP.',
            'data' => $data,
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * Disable 2FA for the current user.
     */
    public function disable(DisableTwoFactorRequest $request, DisableTwoFactorHandler $handler): JsonResponse
    {
        $handler(
            command: new DisableTwoFactor(
                user: auth()->user()
            )
        );

        return response()->json([
            'status' => 'success',
            'message' => '2FA disabled for this user.',
        ], Response::HTTP_NO_CONTENT);
    }

    /**
     * Confirm 2FA for the current user.
     */
    public function confirm(ConfirmTwoFactorRequest $request, ConfirmTwoFactorHandler $handler): JsonResponse
    {
        $passed = $handler(
            command: new ConfirmTwoFactor(
                user: auth()->user(),
                code: $request->input('code')
            )
        );

        return response()->json([
            'status' => 'success',
            'message' => $passed ? '2FA verified successfully.' : 'Invalid 2FA code.',
            'data' => [
                'verified' => $passed,
            ],
        ]);
    }

    /**
     * Get recovery codes for the current user.
     */
    public function recoveryCodes(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Recovery codes generated successfully.',
            'data' => auth()->user()->recoveryCodes(),
        ]);
    }
}
