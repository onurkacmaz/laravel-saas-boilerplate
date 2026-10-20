<?php

namespace Features\Auth\Register\Events;

use Illuminate\Auth\Events\Failed;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserRegisterFailed extends Failed
{
    use Dispatchable, SerializesModels;
}
