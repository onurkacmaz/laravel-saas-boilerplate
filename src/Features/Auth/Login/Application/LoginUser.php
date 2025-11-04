<?php

namespace Features\Auth\Login\Application;

readonly class LoginUser
{
    public function __construct(
        public string $email,
        public string $password,
    ) {}
}
