<?php

namespace Features\Auth\Login\Application;

use App\Models\User;
use Features\Auth\Login\Events\LoginSucceeded;
use Features\Auth\Login\Events\RecoveryCodeReplaced;
use Features\Auth\Login\Events\TwoFactorAuthenticationFailed;
use Features\Auth\Login\Events\ValidTwoFactorAuthenticationCodeProvided;
use Features\Auth\Login\Exceptions\InvalidJWTException;
use Features\Auth\TwoFactorAuth\Infrastructure\Support\TwoFactor;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use JsonException;
use Throwable;

class TwoFactorLoginHandler
{
    public function __construct(
        private readonly TwoFactor $twoFactor
    ) {}

    /**
     * @return array{0: User, 1: string}
     *
     * @throws InvalidJWTException
     * @throws JsonException
     */
    public function __invoke(LoginWithTwoFactor $command): array
    {
        $user = $this->challengedUser($command->jwt);

        if ($command->recoveryCode !== null && $code = $this->validRecoveryCode($user, $command->recoveryCode)) {
            $user->replaceRecoveryCode($code);

            event(new RecoveryCodeReplaced($user, $code));

            return $this->completeLogin($user);
        }

        if ($command->code === null || ! $this->hasValidCode($user, $command->code)) {
            event(new TwoFactorAuthenticationFailed($user));

            throw new InvalidJWTException;
        }

        event(new ValidTwoFactorAuthenticationCodeProvided($user));

        return $this->completeLogin($user);
    }

    /**
     * @return array{0: User, 1: string}
     */
    private function completeLogin(User $user): array
    {
        $token = LoginUserHandler::issueToken($user);

        LoginSucceeded::dispatch('sanctum', $user, false);

        return [$user, $token];
    }

    /**
     * Resolve the user the challenge token was issued for.
     *
     * @throws InvalidJWTException
     */
    private function challengedUser(string $jwt): User
    {
        // Validate iat/nbf/exp against the application clock, the same clock
        // used to issue the token (see User::jwtPayload()).
        JWT::$timestamp = now()->getTimestamp();

        try {
            $decoded = JWT::decode($jwt, new Key(User::jwtSecret(), 'HS256'));
        } catch (Throwable) {
            throw new InvalidJWTException;
        } finally {
            JWT::$timestamp = null;
        }

        // Only tokens issued for a 2FA challenge are accepted here.
        if (($decoded->purpose ?? null) !== '2fa' || empty($decoded->sub) || ! is_string($decoded->sub)) {
            throw new InvalidJWTException;
        }

        $user = User::query()->find($decoded->sub);

        // The user may have been deleted or may have disabled 2FA since the
        // challenge was issued.
        if (! $user || ! $user->hasTwoFactorEnabled()) {
            throw new InvalidJWTException;
        }

        return $user;
    }

    /**
     * @throws JsonException
     */
    private function validRecoveryCode(User $user, string $recoveryCode): ?string
    {
        $recoveryCode = trim($recoveryCode);

        foreach ($user->recoveryCodes() as $code) {
            if (hash_equals((string) $code, $recoveryCode)) {
                return $code;
            }
        }

        return null;
    }

    private function hasValidCode(User $user, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if ($code === '' || ! $this->twoFactor->verifyOtp(secret: (string) $user->two_factor_secret, otp: $code)) {
            return false;
        }

        // A TOTP code may only be used once inside its validity window, so an
        // intercepted code cannot be replayed.
        return Cache::add('2fa-used:'.$user->id.':'.$code, true, now()->addMinutes(2));
    }
}
