<?php

namespace Features\Auth\TwoFactorAuth\Application;

use App\Models\User;
use Features\Auth\TwoFactorAuth\Events\TwoFactorEnabled;
use Features\Auth\TwoFactorAuth\Infrastructure\Support\TwoFactor;
use Random\RandomException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class EnableTwoFactorHandler
{
    /**
     * Start the 2FA enrolment: generate a secret the user must confirm with a
     * valid code before two-factor authentication becomes active.
     *
     * @throws RandomException
     */
    public function __invoke(EnableTwoFactor $command): array
    {
        /** @var User $user */
        $user = $command->user;

        if ($user->hasTwoFactorEnabled()) {
            throw new ConflictHttpException('Two-factor authentication is already enabled. Disable it first to re-enroll.');
        }

        $twoFactor = app(TwoFactor::class);

        if (! $user->two_factor_enabled || empty($user->two_factor_secret)) {
            $user->forceFill([
                'two_factor_secret' => $twoFactor->generateTwoFactorSecret(),
                'two_factor_enabled' => true,
                'two_factor_verified' => false,
            ])->save();
        }

        event(new TwoFactorEnabled($user));

        return [
            'secret' => $user->two_factor_secret,
            'totp_uri' => $twoFactor->provisioningUri((string) config('app.name'), $user->email, $user->two_factor_secret),
        ];
    }
}
