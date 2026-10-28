<?php

namespace Features\Subscription\Infrastructure\Paddle;

use Laravel\Paddle\Transaction;

class PaddleTransaction extends Transaction
{
    protected $table = 'paddle_transactions';
}
