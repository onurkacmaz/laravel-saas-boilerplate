<?php

namespace Features\Auth\Login\Application;

readonly class LoginWithTwoFactor
{
    public function __construct(
        public string $jwt,
        public string $code,
        public ?string $recoveryCode = null,
    ) {}
}
