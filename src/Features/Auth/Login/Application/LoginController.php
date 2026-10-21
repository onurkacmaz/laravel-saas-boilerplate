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
     * When two-factor authentication is enabled, a 202 response is returned
     * with a short-lived challenge token (`data.token`) that must be sent to
     * the `2fa/challenge` endpoint together with a TOTP or recovery code.
     *
     * @throws InvalidCredentialsException
     */
    public function __invoke(LoginRequest $request, LoginUserHandler $handler)
    {
        [$user, $token] = $handler(
            command: new LoginUser(
                email: (string) $request->input('email'),
                password: (string) $request->input('password'),
            )
        );

        if ($token === null) {
            return $user->toResource(UserResource::class)->additional([
                'status' => 'success',
                'message' => __('Two-factor authentication required. Please provide the code from your authenticator app.'),
                'data' => [
                    'token' => $user->createJwtToken(),
                    'two_factor_required' => true,
                ],
            ])->response()->setStatusCode(Response::HTTP_ACCEPTED);
        }

        return $user->toResource(UserResource::class)->additional([
            'data' => [
                'token' => $token,
                'two_factor_required' => false,
            ],
            'status' => 'success',
            'message' => __('User logged in successfully'),
        ]);
    }
}
