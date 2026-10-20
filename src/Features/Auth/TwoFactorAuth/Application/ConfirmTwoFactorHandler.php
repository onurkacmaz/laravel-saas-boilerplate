<?php

namespace Features\Auth\TwoFactorAuth\Application;

use Features\Auth\TwoFactorAuth\Events\InvalidTwoFactorCode;
use Features\Auth\TwoFactorAuth\Events\TwoFactorConfirmed;
use Features\Auth\TwoFactorAuth\Infrastructure\Support\TwoFactor;
use JsonException;

class ConfirmTwoFactorHandler
{
    /**
     * Confirm the 2FA enrolment with a code from the authenticator app.
     *
     * Two-factor authentication is only activated (and recovery codes only
     * generated) when the code is valid.
     *
     * @throws JsonException
     */
    public function __invoke(ConfirmTwoFactor $command): bool
    {
        $user = $command->user;

        if (! $user->two_factor_enabled || ! $user->two_factor_secret) {
            abort(400, 'Two-factor authentication is not enabled for this user.');
        }

        $passed = app(TwoFactor::class)->verifyOtp(
            secret: $user->two_factor_secret,
            otp: $command->code
        );

        if (! $passed) {
            InvalidTwoFactorCode::dispatch($user);

            return false;
        }

        $user->forceFill(['two_factor_verified' => true])->save();
        $user->regenerateRecoveryCodes();

        TwoFactorConfirmed::dispatch($user);

        return true;
    }
}
