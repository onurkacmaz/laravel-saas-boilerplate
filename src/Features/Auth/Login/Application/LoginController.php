<?php

namespace Features\Auth\Login\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Auth\Login\Exceptions\InvalidCredentialsException;
use Features\Auth\Shared\Resources\UserResource;
use Symfony\Component\HttpFoundation\Response;

#[Group('Authentication')]
class LoginController
{
    /**
     * Log in a user.
     *
     * @throws InvalidCredentialsException
     */
    public function __invoke(LoginRequest $request, LoginUserHandler $handler)
    {
        [$user, $token] = $handler(
            command: new LoginUser(
                email: $request->input('email'),
                password: $request->input('password'),
            )
        );

        if ($user->two_factor_enabled && $user->two_factor_verified) {
            return $user->toResource(UserResource::class)->additional([
                'status' => 'success',
                'message' => '2FA enabled for this user. Please check your email for the OTP.',
                'data' => [
                    'token' => $user->createJwtToken(),
                ],
            ])->response()->setStatusCode(Response::HTTP_ACCEPTED);
        }

        return $user->toResource(UserResource::class)->additional([
            'data' => [
                'token' => $token,
            ],
            'status' => 'success',
            'message' => 'User logged in successfully',
        ]);
    }
}
