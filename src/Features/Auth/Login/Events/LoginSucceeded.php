<?php

namespace Features\Auth\Login\Events;

use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoginSucceeded extends Login
{
    use Dispatchable, SerializesModels;
}
