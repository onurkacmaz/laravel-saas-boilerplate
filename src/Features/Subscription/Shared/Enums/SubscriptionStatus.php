<?php

namespace Features\Subscription\Shared\Enums;

enum SubscriptionStatus: string
{
    case ACTIVE = 'active';
    case TRIALING = 'trialing';
    case PAST_DUE = 'past_due';
    case CANCELED = 'canceled';
    case EXPIRED = 'expired';

    public static function fromStripeStatus(?string $status): self
    {
        return match ($status) {
            'trialing' => self::TRIALING,
            'active' => self::ACTIVE,
            'past_due', 'unpaid', 'incomplete' => self::PAST_DUE,
            'canceled' => self::CANCELED,
            'incomplete_expired', 'ended' => self::EXPIRED,
            null => self::CANCELED,
            default => self::PAST_DUE,
        };
    }

    public function toStripeStatus(): string
    {
        return match ($this) {
            self::ACTIVE => 'active',
            self::TRIALING => 'trialing',
            self::PAST_DUE => 'past_due',
            self::CANCELED => 'canceled',
            self::EXPIRED => 'ended',
        };
    }
}
