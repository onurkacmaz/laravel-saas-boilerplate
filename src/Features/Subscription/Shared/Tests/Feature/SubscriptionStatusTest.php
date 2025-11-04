<?php

use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('marks a subscription as expired when the end date has passed', function (): void {
    $subscription = Subscription::factory()->create([
        'ends_at' => now()->subDay(),
        'stripe_status' => 'canceled',
    ]);

    expect($subscription->isExpired())->toBeTrue();
});

it('marks a subscription as active when within the billing period', function (): void {
    $subscription = Subscription::factory()->create([
        'stripe_status' => 'active',
        'ends_at' => null,
    ]);

    expect($subscription->isActive())->toBeTrue();
});

it('calculates remaining days in the subscription correctly', function (): void {
    Carbon::setTestNow($now = now()->startOfDay());

    $subscription = Subscription::factory()->create([
        'stripe_status' => 'active',
        'starts_at' => $now,
        'ends_at' => $now->copy()->addDays(15),
    ]);

    expect($subscription->remainingDays())->toBe(15);

    Carbon::setTestNow();
});
