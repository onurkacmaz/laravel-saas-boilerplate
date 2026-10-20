<?php

namespace Features\Auth\Login\Events;

use Illuminate\Auth\Events\Failed;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoginFailed extends Failed
{
    use Dispatchable, SerializesModels;
}
