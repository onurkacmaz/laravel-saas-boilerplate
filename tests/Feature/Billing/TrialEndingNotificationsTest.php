<?php

use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
use Features\Subscription\Shared\Notifications\TrialEndingSoonNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('notifies owners whose trial ends in three days', function (string $time): void {
    Notification::fake();
    $this->travelTo(now()->setTimeFromTimeString($time));

    $endingSoon = Subscription::factory()->trialing()->create(['trial_ends_at' => now()->addDays(3)->setTime(12, 0)]);
    $endingLater = Subscription::factory()->trialing()->create(['trial_ends_at' => now()->addDays(10)]);
    $canceled = Subscription::factory()->create([
        'stripe_status' => 'canceled',
        'trial_ends_at' => now()->addDays(3)->setTime(12, 0),
    ]);

    $this->artisan('subscriptions:trial-ending')
        ->expectsOutput('Sent 1 trial ending notifications.')
        ->assertSuccessful();

    Notification::assertSentTo($endingSoon->organization->owner, TrialEndingSoonNotification::class, function ($notification) use ($endingSoon) {
        $mail = $notification->toMail($endingSoon->organization->owner);
        $data = $notification->toArray($endingSoon->organization->owner);

        return $data['days_remaining'] === 3
            && str_contains(implode(' ', $mail->introLines), '(3 days remaining)');
    });
    Notification::assertNotSentTo($endingLater->organization->owner, TrialEndingSoonNotification::class);
    Notification::assertNotSentTo($canceled->organization->owner, TrialEndingSoonNotification::class);
})->with(['just after midnight' => '00:05', 'in the morning' => '09:30', 'in the afternoon' => '15:00', 'late at night' => '23:55']);

it('renders trial notifications without a plan', function (): void {
    $subscription = Subscription::factory()->trialing()->create(['plan_id' => null]);
    $owner = $subscription->organization->owner;
    $notification = new TrialEndingSoonNotification($subscription->fresh());

    expect($notification->via($owner))->toBe(['mail'])
        ->and($notification->toMail($owner)->subject)->toBe('Your '.config('app.name').' trial is ending soon')
        ->and($notification->toArray($owner)['plan_name'])->toBeNull();
});
