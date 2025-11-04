<?php

namespace Features\Auth\TwoFactorAuth\Application;

use Features\Auth\TwoFactorAuth\Events\InvalidTwoFactorCode;
use Features\Auth\TwoFactorAuth\Events\TwoFactorConfirmed;
use Features\Auth\TwoFactorAuth\Infrastructure\Support\TwoFactor;
use Illuminate\Support\Str;

class ConfirmTwoFactorHandler
{
    public function __invoke(ConfirmTwoFactor $command): bool
    {
        if (! $command->user->two_factor_enabled || ! $command->user->two_factor_secret) {
            abort(400, 'Two-factor authentication is not enabled for this user.');
        }

        $passed = app(TwoFactor::class)->verifyOtp(
            secret: $command->user->two_factor_secret,
            otp: $command->code
        );

        if (! $passed) {
            InvalidTwoFactorCode::dispatch($command->user);
        }

        $command->user->update(
            attributes: [
                'two_factor_verified' => true,
                'two_factor_recovery_codes' => json_encode(
                    value: collect(range(1, 8))
                        ->map(fn () => encrypt(Str::random(10).'-'.Str::random(10)))
                        ->all()
                ),
            ]
        );

        TwoFactorConfirmed::dispatch($command->user);

        return $passed;
    }
}
