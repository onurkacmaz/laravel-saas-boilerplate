<?php

use App\Models\Organization;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
use Features\Subscription\Infrastructure\Eloquent\Models\Usage;
use Features\Subscription\Shared\Support\ResourceLimits;
use Features\Subscription\Shared\Support\UsageTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Infrastructure\Tenant\TenantContext;

uses(RefreshDatabase::class);

afterEach(fn () => TenantContext::clear());

it('tracks and aggregates usage per period', function (): void {
    $organization = Organization::factory()->create();
    $tracker = new UsageTracker($organization);

    $this->travel(-40)->days();
    $tracker->track('api_calls', 100);
    $this->travelBack();

    $usage = $tracker->track('api_calls', 5, ['endpoint' => '/v1/user']);
    $tracker->track('api_calls', 7);
    (new UsageTracker(Organization::factory()->create()))->track('api_calls', 1000);

    expect($usage)->toBeInstanceOf(Usage::class)
        ->and($usage->organization->is($organization))->toBeTrue()
        ->and($usage->metadata)->toBe(['endpoint' => '/v1/user'])
        ->and($tracker->getTotal('api_calls'))->toBe(112)
        ->and($tracker->getCurrentPeriodTotal('api_calls'))->toBe(12)
        ->and($tracker->getCurrentCount('api_calls'))->toBe(12)
        ->and($tracker->getCurrentCount('projects'))->toBe(0);
});

it('counts members and compares them with the plan limits', function (): void {
    [, $organization] = userInOrganization('admin');
    userInOrganization('member', $organization);
    $plan = Plan::factory()->create(['limits' => ['users' => 2, 'projects' => -1]]);
    Subscription::factory()->create(['organization_id' => $organization->id, 'plan_id' => $plan->id]);

    $tracker = new UsageTracker($organization);

    expect($tracker->getCurrentCount('users'))->toBe(2)
        ->and($tracker->isWithinLimit('users'))->toBeFalse()
        ->and($tracker->isWithinLimit('users', 1))->toBeTrue()
        ->and($tracker->getSummary())->toMatchArray([
            'users' => ['current' => 2, 'limit' => 2, 'remaining' => 0, 'percentage' => 100, 'can_add' => false],
            'projects' => ['current' => 0, 'limit' => -1, 'remaining' => -1, 'percentage' => 0, 'can_add' => true],
        ]);
});

it('behaves safely without an organization', function (): void {
    $tracker = new UsageTracker;

    expect($tracker->getTotal('users'))->toBe(0)
        ->and($tracker->getCurrentPeriodTotal('users'))->toBe(0)
        ->and($tracker->getCurrentCount('users'))->toBe(0)
        ->and($tracker->getSummary())->toBe([]);

    $tracker->track('users');
})->throws(RuntimeException::class);

it('falls back to default limits', function (): void {
    $limits = new ResourceLimits;

    expect($limits->getAllLimits())->toHaveKeys(['users', 'storage', 'api_calls', 'projects'])
        ->and($limits->getLimit('users', 99))->toBe(99)
        ->and($limits->hasFeature('webhooks'))->toBeFalse();

    $organization = Organization::factory()->create();
    $limits = new ResourceLimits($organization);

    expect($limits->getLimit('users'))->toBe(5)
        ->and($limits->getLimit('unknown', 3))->toBe(3)
        ->and($limits->hasFeature('webhooks'))->toBeFalse();

    Subscription::factory()->create(['organization_id' => $organization->id, 'plan_id' => null]);
    $limits = new ResourceLimits($organization);

    expect($limits->getLimit('users'))->toBe(5)
        ->and($limits->getAllLimits()['users'])->toBe(5)
        ->and($limits->hasFeature('webhooks'))->toBeFalse();
});

it('uses the limits and features of the subscribed plan', function (): void {
    $organization = Organization::factory()->create();
    TenantContext::setOrganization($organization);
    $plan = Plan::factory()->create([
        'features' => ['webhooks'],
        'limits' => ['users' => 10, 'storage' => 0, 'api_calls' => -1],
    ]);
    Subscription::factory()->create(['organization_id' => $organization->id, 'plan_id' => $plan->id]);

    $limits = new ResourceLimits;

    expect($limits->getAllLimits())->toMatchArray(['users' => 10, 'storage' => 0, 'api_calls' => -1])
        ->and($limits->hasFeature('webhooks'))->toBeTrue()
        ->and($limits->hasFeature('sso'))->toBeFalse()
        ->and($limits->canPerform('users', 9))->toBeTrue()
        ->and($limits->hasReached('users', 10))->toBeTrue()
        ->and($limits->getRemaining('users', 4))->toBe(6)
        ->and($limits->getRemaining('api_calls', 1000))->toBe(-1)
        ->and($limits->getUsagePercentage('users', 5))->toBe(50.0)
        ->and($limits->getUsagePercentage('users', 50))->toEqual(100)
        ->and($limits->getUsagePercentage('api_calls', 50))->toEqual(0)
        ->and($limits->getUsagePercentage('storage', 0))->toEqual(0)
        ->and($limits->getUsagePercentage('storage', 1))->toEqual(100)
        ->and($limits->hasReached('storage', 0))->toBeTrue()
        ->and($limits->getLimit('projects'))->toBe(1);
});
