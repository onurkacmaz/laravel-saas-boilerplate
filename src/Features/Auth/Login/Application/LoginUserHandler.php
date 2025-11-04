<?php

namespace Features\Auth\Login\Application;

use Features\Auth\Login\Events\LoginFailed;
use Features\Auth\Login\Events\LoginSucceeded;
use Features\Auth\Login\Exceptions\InvalidCredentialsException;

class LoginUserHandler
{
    /**
     * @throws InvalidCredentialsException
     */
    public function __invoke(LoginUser $command): array
    {
        $credentials = [
            'email' => $command->email,
            'password' => $command->password,
        ];

        if (auth()->attempt($credentials)) {
            $user = auth()->user();

            if ($user->two_factor_verified) {
                return [$user, null];
            }

            $token = $user->createToken(
                name: 'auth_token',
                expiresAt: now()->addMinutes(config('auth.token_lifetime'))
            )->plainTextToken;

            LoginSucceeded::dispatch('web', $user, false);

            return [$user, $token];
        }

        LoginFailed::dispatch('web', null, $credentials);

        throw new InvalidCredentialsException('Invalid credentials');
    }
}
