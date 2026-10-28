<?php

namespace Features\Subscription\Infrastructure\Paddle;

use Laravel\Paddle\SubscriptionItem;

class PaddleSubscriptionItem extends SubscriptionItem
{
    protected $table = 'paddle_subscription_items';
}
