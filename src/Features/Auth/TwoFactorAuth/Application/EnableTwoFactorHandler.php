<?php

namespace Features\Auth\TwoFactorAuth\Application;

use App\Models\User;
use Features\Auth\TwoFactorAuth\Events\TwoFactorEnabled;
use Features\Auth\TwoFactorAuth\Infrastructure\Support\TwoFactor;
use Random\RandomException;

class EnableTwoFactorHandler
{
    /**
     * @throws RandomException
     */
    public function __invoke(EnableTwoFactor $command): array
    {
        /**
         * @var User $user
         */
        $user = $command->user;
        if (! $user->two_factor_enabled) {
            $user->forceFill([
                'two_factor_secret' => app(TwoFactor::class)->generateTwoFactorSecret(),
                'two_factor_enabled' => true,
            ])->save();
        }

        event(new TwoFactorEnabled($user));

        return [
            'secret' => $user->two_factor_secret,
            'totp_uri' => 'otpauth://totp/'.config('app.name').':'.$user->email.'?secret='.$user->two_factor_secret.'&issuer='.config('app.name'),
        ];
    }
}
