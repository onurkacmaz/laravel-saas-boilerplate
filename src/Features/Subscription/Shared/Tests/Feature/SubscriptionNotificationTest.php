<?php

use App\Models\Organization;
use App\Models\User;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
use Features\Subscription\Shared\Notifications\SubscriptionCanceledNotification;
use Features\Subscription\Shared\Notifications\SubscriptionCreatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();
});

it('sends subscription created notification to the owner', function (): void {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create(['owner_id' => $owner->id]);
    $plan = Plan::factory()->create();
    $subscription = Subscription::factory()->create([
        'id' => Str::uuid(),
        'organization_id' => $organization->id,
        'plan_id' => $plan->id,
    ]);

    $owner->notify(new SubscriptionCreatedNotification($subscription));

    Notification::assertSentTo($owner, SubscriptionCreatedNotification::class);
});

it('includes subscription details in cancellation notification', function (): void {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create(['owner_id' => $owner->id]);
    $plan = Plan::factory()->create();
    $subscription = Subscription::factory()->create([
        'id' => Str::uuid(),
        'organization_id' => $organization->id,
        'plan_id' => $plan->id,
        'ends_at' => now()->addDays(7),
    ]);

    $owner->notify(new SubscriptionCanceledNotification($subscription));

    Notification::assertSentTo($owner, SubscriptionCanceledNotification::class, function ($notification) use ($subscription) {
        return $notification->subscription->id === $subscription->id;
    });
});
