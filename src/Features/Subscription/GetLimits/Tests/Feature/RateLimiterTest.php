<?php

use App\Models\Organization;
use App\Models\User;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Infrastructure\RateLimiting\RateLimiter;
use Infrastructure\Tenant\TenantContext;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    if (! str_contains($this->name(), 'rate limit middleware')) {
        Cache::flush();
    }
});

it('returns rate limits for the free plan', function (): void {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_id' => $user->id]);
    $freePlan = Plan::factory()->create([
        'slug' => 'free',
        'limits' => [
            'requests_per_minute' => 60,
        ],
    ]);
    Subscription::factory()->create([
        'organization_id' => $organization->id,
        'plan_id' => $freePlan->id,
        'stripe_status' => 'active',
    ]);

    TenantContext::setOrganization($organization);

    $rateLimiter = app(RateLimiter::class);
    $limits = $rateLimiter->getPlanRateLimit();

    expect($limits['max_attempts'])->toBe(60)
        ->and($limits['decay_minutes'])->toBe(1)
        ->and($limits['description'])->toContain('60 requests');
});

it('returns rate limits for the pro plan', function (): void {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_id' => $user->id]);
    $proPlan = Plan::factory()->create([
        'slug' => 'pro',
        'limits' => [
            'requests_per_minute' => 1000,
        ],
    ]);
    Subscription::factory()->create([
        'organization_id' => $organization->id,
        'plan_id' => $proPlan->id,
        'stripe_status' => 'active',
    ]);

    TenantContext::setOrganization($organization);

    $rateLimiter = app(RateLimiter::class);
    $limits = $rateLimiter->getPlanRateLimit();

    expect($limits['max_attempts'])->toBe(1000)
        ->and($limits['decay_minutes'])->toBe(1);
});

it('blocks requests after the limit is exceeded', function (): void {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_id' => $user->id]);
    $plan = Plan::factory()->create([
        'slug' => 'free',
        'limits' => [
            'requests_per_minute' => 60,
        ],
    ]);
    Subscription::factory()->create([
        'organization_id' => $organization->id,
        'plan_id' => $plan->id,
        'stripe_status' => 'active',
    ]);

    TenantContext::setOrganization($organization);

    $rateLimiter = app(RateLimiter::class);
    $key = 'test-key';

    for ($i = 0; $i < 60; $i++) {
        $rateLimiter->hit($key);
    }

    expect($rateLimiter->tooManyAttempts($key))->toBeTrue()
        ->and($rateLimiter->retriesLeft($key))->toBe(0);
});

it('applies plan limits through the rate limit middleware', function (): void {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_id' => $user->id]);

    $plan = Plan::factory()->create([
        'slug' => 'free',
        'limits' => [
            'requests_per_minute' => 10,
        ],
    ]);
    Subscription::factory()->create([
        'organization_id' => $organization->id,
        'plan_id' => $plan->id,
        'stripe_status' => 'active',
    ]);

    $this->actingAs($user);

    $responseWithoutOrg = $this->getJson('/api/v1/user');
    expect($responseWithoutOrg->headers->get('X-RateLimit-Limit'))->toBe('60');

    $responseWithOrg = $this->getJson('/api/v1/user', [
        'X-Organization-Id' => $organization->id,
    ]);

    expect($responseWithOrg->status())->toBe(200)
        ->and($responseWithOrg->headers->has('X-RateLimit-Limit'))->toBeTrue()
        ->and($responseWithOrg->headers->get('X-RateLimit-Limit'))->toBe('10');
});

it('includes rate limit headers in responses', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->getJson('/api/v1/user');

    expect($response->headers->has('X-RateLimit-Limit'))->toBeTrue()
        ->and($response->headers->has('X-RateLimit-Remaining'))->toBeTrue()
        ->and($response->headers->has('X-RateLimit-Reset'))->toBeTrue();
});
