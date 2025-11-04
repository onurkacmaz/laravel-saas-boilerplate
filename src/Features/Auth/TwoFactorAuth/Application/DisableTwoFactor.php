<?php

namespace Features\Auth\TwoFactorAuth\Application;

use Illuminate\Contracts\Auth\Authenticatable;

readonly class DisableTwoFactor
{
    public function __construct(
        public Authenticatable $user,
    ) {}
}
