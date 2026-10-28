<?php

namespace Features\Subscription\Infrastructure\Paddle;

use Laravel\Paddle\Customer;

class PaddleCustomer extends Customer
{
    protected $table = 'paddle_customers';
}
