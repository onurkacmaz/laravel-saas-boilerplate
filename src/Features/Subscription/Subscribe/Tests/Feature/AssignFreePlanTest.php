<?php

use App\Models\Organization;
use App\Models\User;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
use Features\Subscription\Subscribe\Application\Subscribe;
use Features\Subscription\Subscribe\Application\SubscribeHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function registerFounder(): Organization
{
    test()->postJson('/api/v1/auth/register', [
        'name' => 'Founder',
        'email' => 'founder@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertCreated();

    return User::query()->where('email', 'founder@example.com')->sole()->organizations()->sole();
}

function freePlan(): Plan
{
    return Plan::factory()->create(['price' => 0, 'stripe_price_id' => null, 'trial_days' => 0, 'is_active' => true]);
}

it('puts the personal organization of a new account on the free plan', function (): void {
    $plan = freePlan();
    Plan::factory()->create(['price' => 2900]);

    $organization = registerFounder();

    expect($organization->activeSubscription()?->plan_id)->toBe($plan->id);
});

it('logs a warning and still registers when no free plan exists', function (): void {
    Log::spy();

    $organization = registerFounder();

    expect($organization->hasActiveSubscription())->toBeFalse();
    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context) => str_contains($message, 'subscriptions:assign-free-plan')
            && $context === ['organization_id' => $organization->id])
        ->once();
});

it('still registers when subscribing to the free plan fails', function (): void {
    Exceptions::fake();
    freePlan();

    $this->mock(SubscribeHandler::class)
        ->shouldReceive('__invoke')
        ->once()
        ->andThrow(new RuntimeException('Billing is down.'));

    $organization = registerFounder();

    expect($organization->hasActiveSubscription())->toBeFalse();
    Exceptions::assertReported(fn (RuntimeException $exception) => $exception->getMessage() === 'Billing is down.');
});

it('puts organizations without an active plan on the free plan', function (): void {
    $free = freePlan();
    $paid = Plan::factory()->create(['price' => 2900]);
    $withoutPlan = Organization::factory()->count(3)->create();
    $onPaidPlan = Organization::factory()->create();
    $onPaidPlan->subscriptions()->create([
        'type' => 'default',
        'name' => 'default',
        'plan_id' => $paid->id,
        'stripe_id' => Subscription::makeLocalStripeId(),
        'stripe_status' => 'active',
    ]);

    $this->artisan('subscriptions:assign-free-plan')
        ->expectsOutput('Put 3 organization(s) on the free plan.')
        ->assertSuccessful();

    $withoutPlan->each(fn (Organization $organization) => expect($organization->activeSubscription()?->plan_id)->toBe($free->id));
    expect($onPaidPlan->activeSubscription()?->plan_id)->toBe($paid->id)
        ->and($onPaidPlan->subscriptions()->count())->toBe(1);
});

it('lists the organizations without an active plan on a dry run', function (): void {
    freePlan();
    $organization = Organization::factory()->create(['name' => 'Acme']);

    $this->artisan('subscriptions:assign-free-plan', ['--dry-run' => true])
        ->expectsOutput("{$organization->id}  Acme")
        ->expectsOutput('1 organization(s) have no active plan.')
        ->assertSuccessful();

    expect($organization->hasActiveSubscription())->toBeFalse();
});

it('fails when there is no free plan to assign', function (): void {
    Organization::factory()->create();

    $this->artisan('subscriptions:assign-free-plan')
        ->expectsOutput('No active free plan exists. Seed the plan catalogue first: php artisan db:seed')
        ->assertFailed();
});

it('reports the organizations it could not subscribe', function (): void {
    Exceptions::fake();
    freePlan();
    $organization = Organization::factory()->create(['name' => 'Acme']);

    $this->mock(SubscribeHandler::class)
        ->shouldReceive('__invoke')
        ->once()
        ->withArgs(fn (Subscribe $command) => $command->organization?->is($organization))
        ->andThrow(new RuntimeException('Billing is down.'));

    $this->artisan('subscriptions:assign-free-plan')
        ->expectsOutput("Could not subscribe {$organization->id} (Acme). See the log.")
        ->expectsOutput('Put 0 organization(s) on the free plan.')
        ->assertFailed();
});
