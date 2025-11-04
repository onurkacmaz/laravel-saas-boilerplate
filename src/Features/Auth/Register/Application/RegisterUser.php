<?php

namespace Features\Auth\Register\Application;

readonly class RegisterUser
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {}
}
