<?php

use App\Models\Organization;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
use Features\Subscription\Shared\Notifications\PaymentFailedNotification;
use Features\Subscription\Shared\Notifications\PaymentSucceededNotification;
use Features\Subscription\Shared\Notifications\SubscriptionCanceledNotification;
use Features\Subscription\Shared\Notifications\SubscriptionCreatedNotification;
use Features\Subscription\Shared\Notifications\SubscriptionUpgradedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => config(['app.frontend_url' => 'https://app.example.test']));

function mailText($message): string
{
    return implode("\n", [...$message->introLines, ...$message->outroLines]);
}

it('renders payment notifications', function (): void {
    $organization = Organization::factory()->create(['name' => 'Acme']);
    $owner = $organization->owner;

    $failed = new PaymentFailedNotification($organization, [
        'id' => 'in_1', 'amount_due' => 2900, 'currency' => 'eur',
        'last_payment_error' => ['message' => 'Card declined'],
    ]);
    $succeeded = new PaymentSucceededNotification($organization, ['id' => 'pi_1', 'amount_received' => 1050, 'currency' => 'usd']);

    expect($failed->via($owner))->toBe(['mail', 'database'])
        ->and(mailText($failed->toMail($owner)))->toContain('Amount: 29.00 EUR')->toContain('Organization: Acme')
        ->and($failed->toMail($owner)->actionUrl)->toBe('https://app.example.test/settings/billing')
        ->and($failed->toArray($owner))->toMatchArray(['amount' => 2900, 'failure_message' => 'Card declined', 'payment_id' => 'in_1'])
        ->and(mailText($succeeded->toMail($owner)))->toContain('Amount: 10.50 USD')
        ->and($succeeded->toMail($owner)->actionUrl)->toBe('https://app.example.test/dashboard')
        ->and($succeeded->toArray($owner))->toMatchArray(['payment_id' => 'pi_1', 'currency' => 'usd']);

    // Notifications are stored for the in-app inbox as well.
    $owner->notify($failed);
    expect($owner->notifications()->count())->toBe(1);
});

it('renders subscription notifications', function (): void {
    $basic = Plan::factory()->create(['name' => 'Basic', 'limits' => ['users' => 3]]);
    $pro = Plan::factory()->create(['name' => 'Pro', 'limits' => ['users' => -1, 'storage' => 2048, 'api_calls' => 1000, 'projects' => 10]]);
    $subscription = Subscription::factory()->trialing()->create(['plan_id' => $pro->id])->fresh(['plan', 'organization']);
    $owner = $subscription->organization->owner;

    $created = new SubscriptionCreatedNotification($subscription);
    $upgraded = new SubscriptionUpgradedNotification($subscription, $basic);

    expect($created->toMail($owner)->subject)->toBe('Welcome to Pro!')
        ->and(mailText($created->toMail($owner)))->toContain('Users:** Unlimited')->toContain('2,048 MB')->toContain('trial period is active')
        ->and($created->toArray($owner))->toMatchArray(['plan_name' => 'Pro'])
        ->and($upgraded->via($owner))->toBe(['mail'])
        ->and($upgraded->toMail($owner)->subject)->toBe("You've upgraded to Pro!")
        ->and(mailText($upgraded->toMail($owner)))->toContain('from **Basic** to **Pro**')
        ->and($upgraded->toArray($owner))->toMatchArray(['new_plan_name' => 'Pro', 'old_plan_name' => 'Basic']);
});

it('renders cancellation notifications with and without a grace period', function (): void {
    $subscription = Subscription::factory()->create(['ends_at' => now()->addDays(10)])->fresh(['plan', 'organization']);
    $owner = $subscription->organization->owner;

    $graceful = new SubscriptionCanceledNotification($subscription);

    expect($graceful->via($owner))->toBe(['mail'])
        ->and(mailText($graceful->toMail($owner)))->toContain('continue to have access')
        ->and($graceful->toMail($owner)->actionUrl)->toBe('https://app.example.test/plans')
        ->and($graceful->toArray($owner)['subscription_id'])->toBe($subscription->id);

    $immediate = new SubscriptionCanceledNotification(
        Subscription::factory()->create(['plan_id' => null, 'ends_at' => null])->fresh(['plan', 'organization'])
    );

    expect(mailText($immediate->toMail($owner)))->toContain('immediately revoked')
        ->and($immediate->toMail($owner)->subject)->toBe('Your '.config('app.name').' subscription has been canceled')
        ->and($immediate->toArray($owner)['plan_name'])->toBeNull();
});
