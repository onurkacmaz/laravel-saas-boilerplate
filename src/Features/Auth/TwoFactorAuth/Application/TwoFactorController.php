<?php

namespace Features\Auth\TwoFactorAuth\Application;

use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;
use Random\RandomException;
use Symfony\Component\HttpFoundation\Response;

#[Group('Two Factor Authentication')]
class TwoFactorController
{
    /**
     * Enable 2FA for the current user.
     *
     * Returns the secret and an `otpauth://` URI to be scanned by an
     * authenticator app. 2FA becomes active once confirmed with a valid code.
     *
     * @throws RandomException
     */
    public function enable(EnableTwoFactorRequest $request, EnableTwoFactorHandler $handler): JsonResponse
    {
        $data = $handler(
            command: new EnableTwoFactor(
                user: $request->user()
            )
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Scan the QR code with your authenticator app, then confirm with a code to finish enabling 2FA.',
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
                user: $request->user()
            )
        );

        return response()->json([
            'status' => 'success',
            'message' => '2FA disabled for this user.',
        ]);
    }

    /**
     * Confirm 2FA for the current user.
     *
     * @throws JsonException
     */
    public function confirm(ConfirmTwoFactorRequest $request, ConfirmTwoFactorHandler $handler): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $passed = $handler(
            command: new ConfirmTwoFactor(
                user: $user,
                code: (string) $request->input('code')
            )
        );

        if (! $passed) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid 2FA code.',
                'data' => [
                    'verified' => false,
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'status' => 'success',
            'message' => '2FA verified successfully. Store your recovery codes in a safe place.',
            'data' => [
                'verified' => true,
                'recovery_codes' => $user->recoveryCodes(),
            ],
        ]);
    }

    /**
     * Get recovery codes for the current user.
     *
     * @throws JsonException
     */
    public function recoveryCodes(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Two-factor authentication is not enabled for this user.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Recovery codes retrieved successfully.',
            'data' => $user->recoveryCodes(),
        ]);
    }

    /**
     * Regenerate recovery codes for the current user.
     *
     * All previously issued recovery codes become invalid.
     *
     * @throws JsonException
     */
    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Two-factor authentication is not enabled for this user.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Recovery codes regenerated successfully.',
            'data' => $user->regenerateRecoveryCodes(),
        ]);
    }
}
