<?php

namespace Features\Auth\Login\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Auth\Login\Exceptions\InvalidJWTException;
use Features\Auth\Shared\Resources\UserResource;

#[Group('Authentication')]
class TwoFactorAuthenticationController
{
    /**
     * 2FA Challenge.
     *
     * Exchange the challenge token returned by the login endpoint, plus a TOTP
     * code or a recovery code, for an API token.
     *
     * @throws InvalidJWTException
     */
    public function __invoke(TwoFactorLoginRequest $request, TwoFactorLoginHandler $handler)
    {
        [$user, $token] = $handler(
            command: new LoginWithTwoFactor(
                jwt: (string) $request->input('jwt'),
                code: $request->filled('code') ? (string) $request->input('code') : null,
                recoveryCode: $request->filled('recovery_code') ? (string) $request->input('recovery_code') : null,
            )
        );

        return $user->toResource(UserResource::class)->additional([
            'data' => [
                'token' => $token,
            ],
            'status' => 'success',
            'message' => __('2FA verified for this user.'),
        ]);
    }
}
