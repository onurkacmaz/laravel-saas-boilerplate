<?php

namespace Features\Auth\Login\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Auth\Login\Exceptions\InvalidCredentialsException;
use Features\Auth\Login\Exceptions\InvalidJWTException;
use Features\Auth\Shared\Resources\UserResource;

#[Group('Authentication')]
class TwoFactorAuthenticationController
{
    /**
     * 2FA Challenge.
     *
     * @throws InvalidCredentialsException
     * @throws InvalidJWTException
     */
    public function __invoke(TwoFactorLoginRequest $request, TwoFactorLoginHandler $handler)
    {
        [$user, $token] = $handler(
            command: new LoginWithTwoFactor(
                jwt: $request->input('jwt'),
                code: $request->input('code'),
                recoveryCode: $request->input('recovery_code'),
            )
        );

        return $user->toResource(UserResource::class)->additional([
            'data' => [
                'token' => $token,
            ],
            'status' => 'success',
            'message' => '2FA verified for this user.',
        ]);
    }
}
