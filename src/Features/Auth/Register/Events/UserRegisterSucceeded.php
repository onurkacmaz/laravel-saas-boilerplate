<?php

namespace Features\Auth\Register\Events;

use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserRegisterSucceeded extends Registered
{
    use Dispatchable, SerializesModels;
}
