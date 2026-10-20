<?php

namespace Features\Auth\Login\Application;

use App\Models\User;
use Features\Auth\Login\Events\LoginFailed;
use Features\Auth\Login\Events\LoginSucceeded;
use Features\Auth\Login\Exceptions\InvalidCredentialsException;
use Illuminate\Support\Facades\Hash;

class LoginUserHandler
{
    /**
     * Validate the credentials and issue an API token.
     *
     * Returns [$user, $token]. When the user has two-factor authentication
     * enabled the token is null and the caller must start a 2FA challenge.
     *
     * The session guard is deliberately not used: this is a stateless API and
     * attempting a session login would write to a session nobody persists.
     *
     * @throws InvalidCredentialsException
     */
    public function __invoke(LoginUser $command): array
    {
        $email = User::normalizeEmail($command->email);

        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        // Always run a hash check so response time does not reveal whether the
        // e-mail address exists.
        $passwordMatches = Hash::check($command->password, $user?->password ?? self::dummyHash());

        if (! $user || ! $passwordMatches) {
            LoginFailed::dispatch('sanctum', $user, ['email' => $email]);

            throw new InvalidCredentialsException('Invalid credentials');
        }

        if ($user->hasTwoFactorEnabled()) {
            return [$user, null];
        }

        $token = self::issueToken($user);

        LoginSucceeded::dispatch('sanctum', $user, false);

        return [$user, $token];
    }

    public static function issueToken(User $user): string
    {
        return $user->createToken(
            name: 'auth_token',
            expiresAt: now()->addMinutes((int) config('auth.token_lifetime', 3600))
        )->plainTextToken;
    }

    private static function dummyHash(): string
    {
        static $hash = null;

        return $hash ??= Hash::make('dummy-password-for-timing-attack-mitigation');
    }
}
