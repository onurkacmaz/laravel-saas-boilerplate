<?php

namespace Features\Auth\Login\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ValidTwoFactorAuthenticationCodeProvided
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public Authenticatable $authenticatable)
    {
        //
    }
}
