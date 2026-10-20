<?php

namespace Features\Auth\Logout\Application;

use App\Models\User;

readonly class LogoutUser
{
    public function __construct(
        public User $user,
        public bool $allDevices = false,
    ) {}
}
