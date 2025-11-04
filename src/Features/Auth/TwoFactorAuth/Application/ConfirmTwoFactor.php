<?php

namespace Features\Auth\TwoFactorAuth\Application;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

readonly class ConfirmTwoFactor
{
    public function __construct(
        public Authenticatable|User $user,
        public string $code
    ) {}
}
