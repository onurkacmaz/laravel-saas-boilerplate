<?php

namespace Features\Auth\TwoFactorAuth\Application;

use Features\Auth\TwoFactorAuth\Events\TwoFactorDisabled;

class DisableTwoFactorHandler
{
    public function __invoke(DisableTwoFactor $command): void
    {
        $command->user->forceFill([
            'two_factor_secret' => null,
            'two_factor_enabled' => false,
            'two_factor_verified' => false,
            'two_factor_recovery_codes' => null,
        ])->save();

        event(new TwoFactorDisabled($command->user));
    }
}
