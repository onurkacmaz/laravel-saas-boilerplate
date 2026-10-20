<?php

use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
use Features\Subscription\Shared\Enums\SubscriptionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('maps Stripe statuses to subscription statuses and back', function (?string $stripe, SubscriptionStatus $expected): void {
    expect(SubscriptionStatus::fromStripeStatus($stripe))->toBe($expected);
})->with([
    ['trialing', SubscriptionStatus::TRIALING],
    ['active', SubscriptionStatus::ACTIVE],
    ['past_due', SubscriptionStatus::PAST_DUE],
    ['unpaid', SubscriptionStatus::PAST_DUE],
    ['incomplete', SubscriptionStatus::PAST_DUE],
    ['canceled', SubscriptionStatus::CANCELED],
    ['incomplete_expired', SubscriptionStatus::EXPIRED],
    ['ended', SubscriptionStatus::EXPIRED],
    [null, SubscriptionStatus::CANCELED],
    ['paused', SubscriptionStatus::PAST_DUE],
]);

it('converts statuses back to Stripe statuses', function (): void {
    expect(array_map(fn (SubscriptionStatus $status) => $status->toStripeStatus(), SubscriptionStatus::cases()))
        ->toBe(['active', 'trialing', 'past_due', 'canceled', 'ended']);
});

it('exposes subscription state helpers', function (): void {
    $active = Subscription::factory()->active()->create();
    $trialing = Subscription::factory()->trialing()->create();
    $canceled = Subscription::factory()->canceled()->create();
    $pastDue = Subscription::factory()->pastDue()->create();

    expect($active->isActive())->toBeTrue()
        ->and($active->hasValidSubscription())->toBeTrue()
        ->and($active->status)->toBe(SubscriptionStatus::ACTIVE)
        ->and($active->user->is($active->organization))->toBeTrue()
        ->and($active->owner->is($active->organization))->toBeTrue()
        ->and($trialing->isTrialing())->toBeTrue()
        ->and($trialing->remainingDays())->toBe(14)
        ->and($canceled->isCanceled())->toBeTrue()
        ->and($canceled->isExpired())->toBeTrue()
        ->and($canceled->remainingDays())->toBe(0)
        ->and($pastDue->isPastDue())->toBeTrue()
        ->and($active->remainingDays())->toBe(0);
});

it('identifies and cancels local subscriptions without calling Stripe', function (): void {
    $local = Subscription::factory()->create(['stripe_id' => Subscription::makeLocalStripeId()]);
    $remote = Subscription::factory()->create(['stripe_id' => 'sub_123']);

    expect($local->isLocal())->toBeTrue()
        ->and($remote->isLocal())->toBeFalse();

    $local->cancel();

    expect($local->fresh()->stripe_status)->toBe('canceled')
        ->and($local->fresh()->ends_at)->not->toBeNull();

    $other = Subscription::factory()->create(['stripe_id' => Subscription::makeLocalStripeId()]);
    $other->cancelNow();

    expect($other->fresh()->isCanceled())->toBeTrue();
});

it('exposes plan helpers', function (): void {
    $plan = Plan::factory()->create([
        'price' => 2950,
        'currency' => 'EUR',
        'features' => ['webhooks'],
        'limits' => ['users' => 5],
        'stripe_price_id' => 'price_1',
        'stripe_product_id' => 'prod_1',
    ]);

    expect($plan->getFormattedPrice())->toBe('29.50 EUR')
        ->and($plan->isFree())->toBeFalse()
        ->and(Plan::factory()->make(['price' => 0])->isFree())->toBeTrue()
        ->and($plan->hasFeature('webhooks'))->toBeTrue()
        ->and($plan->hasFeature('sso'))->toBeFalse()
        ->and($plan->getLimit('users'))->toBe(5)
        ->and($plan->getLimit('projects', 1))->toBe(1)
        ->and($plan->getStripePriceId())->toBe('price_1')
        ->and($plan->getStripeProductId())->toBe('prod_1');

    Subscription::factory()->create(['plan_id' => $plan->id]);

    expect($plan->subscriptions()->count())->toBe(1);
});
