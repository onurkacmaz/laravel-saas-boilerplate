<?php

namespace Features\Auth\Login\Application;

use App\Models\User;
use Exception;
use Features\Auth\Login\Events\RecoveryCodeReplaced;
use Features\Auth\Login\Events\TwoFactorAuthenticationFailed;
use Features\Auth\Login\Events\ValidTwoFactorAuthenticationCodeProvided;
use Features\Auth\Login\Exceptions\InvalidJWTException;
use Features\Auth\TwoFactorAuth\Infrastructure\Support\TwoFactor;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class TwoFactorLoginHandler
{
    /**
     * @throws InvalidJWTException
     */
    public function __invoke(LoginWithTwoFactor $command): array
    {
        $user = $this->challengedUser($command->jwt);

        if (! $this->hasValidJWT($command->jwt)) {
            throw new InvalidJWTException;
        }

        if ($code = $this->validRecoveryCode($command->jwt, $command->recoveryCode)) {
            $user->replaceRecoveryCode($code);
            $token = $user->createToken('auth_token')->plainTextToken;

            event(new RecoveryCodeReplaced($user, $code));

            return [$user, $token];
        } elseif (! $this->hasValidCode($command->jwt, $command->code)) {
            event(new TwoFactorAuthenticationFailed($user));

            throw new InvalidJWTException;
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        event(new ValidTwoFactorAuthenticationCodeProvided($user));

        return [$user, $token];
    }

    /**
     * @throws InvalidJWTException
     */
    private function challengedUser(string $jwt): User
    {
        $decodedJwt = $this->decodeJwt($jwt);

        if (! $this->hasValidJWT($jwt)) {
            throw new InvalidJWTException;
        }

        return User::query()->find($decodedJwt->sub);
    }

    /**
     * @throws InvalidJWTException
     */
    private function validRecoveryCode(string $jwt, ?string $recoveryCode = null): ?string
    {
        if (! $recoveryCode) {
            return false;
        }

        return tap(collect($this->challengedUser($jwt)->recoveryCodes())
            ->first(function ($code) use ($recoveryCode) {
                return hash_equals($code, $recoveryCode) ? $code : null;
            }))->target;
    }

    /**
     * @throws InvalidJWTException
     */
    private function hasValidCode(string $jwt, string $code): bool
    {
        if (! $code) {
            return false;
        }

        return app(TwoFactor::class)->verifyOtp(
            secret: $this->challengedUser($jwt)->two_factor_secret,
            otp: $code
        );
    }

    /**
     * @throws InvalidJWTException
     */
    protected function decodeJwt(string $jwt): object
    {
        try {
            return JWT::decode($jwt, new Key(env('JWT_SECRET'), 'HS256'));
        } catch (Exception) {
            throw new InvalidJWTException;
        }
    }

    /**
     * @throws InvalidJWTException
     */
    public function hasValidJWT(string $jwt): bool
    {
        $decoded = $this->decodeJwt($jwt);

        $user = User::query()->find($decoded->sub);

        return ! is_null($user);
    }
}
