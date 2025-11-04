<?php

namespace Features\Subscription\Shared\Enums;

enum PlanInterval: string
{
    case MONTHLY = 'monthly';
    case YEARLY = 'yearly';
    case LIFETIME = 'lifetime';
}
