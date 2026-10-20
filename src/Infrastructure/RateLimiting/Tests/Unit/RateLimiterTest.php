<?php

use App\Models\Organization;
use App\Models\User;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
use Illuminate\Cache\RateLimiter as CacheRateLimiter;
use Illuminate\Http\Request;
use Infrastructure\RateLimiting\RateLimiter;
use Infrastructure\Tenant\TenantContext;
use Tests\TestCase;

uses(TestCase::class);

afterEach(function (): void {
    Mockery::close();
});

it('falls back to default rate limit when no organization is set', function (): void {
    $limiter = Mockery::mock(CacheRateLimiter::class);
    $tenantContext = Mockery::mock(TenantContext::class);
    $tenantContext->shouldReceive('getOrganization')->andReturn(null);
    $tenantContext->shouldReceive('getUser')->andReturn(null);

    $service = new RateLimiter($limiter, $tenantContext);

    expect($service->getPlanRateLimit())->toMatchArray([
        'max_attempts' => 30,
        'decay_minutes' => 1,
    ]);
});

it('uses free tier rate limit for authenticated users without organization', function (): void {
    $limiter = Mockery::mock(CacheRateLimiter::class);
    $tenantContext = Mockery::mock(TenantContext::class);
    $tenantContext->shouldReceive('getOrganization')->andReturn(null);
    $tenantContext->shouldReceive('getUser')->andReturn(new User);

    $service = new RateLimiter($limiter, $tenantContext);

    expect($service->getPlanRateLimit())->toMatchArray([
        'max_attempts' => 60,
        'decay_minutes' => 1,
    ]);
});

it('detects unlimited limit configurations', function (): void {
    $limiter = Mockery::mock(CacheRateLimiter::class);
    $tenantContext = Mockery::mock(TenantContext::class);

    $service = new RateLimiter($limiter, $tenantContext);

    expect($service->isUnlimited(['max_attempts' => -1]))->toBeTrue()
        ->and($service->isUnlimited(['max_attempts' => 60]))->toBeFalse();
});

it('uses free tier rate limit when organization has no subscription', function (): void {
    $limiter = Mockery::mock(CacheRateLimiter::class);
    $tenantContext = Mockery::mock(TenantContext::class);

    $organization = Mockery::mock(Organization::class);
    $organization->shouldReceive('activeSubscription')->andReturn(null);

    $tenantContext->shouldReceive('getOrganization')->andReturn($organization);

    $service = new RateLimiter($limiter, $tenantContext);

    expect($service->getPlanRateLimit())->toMatchArray([
        'max_attempts' => 60,
        'decay_minutes' => 1,
    ]);
});

it('derives rate limit from subscription plan details', function (): void {
    $limiter = Mockery::mock(CacheRateLimiter::class);
    $tenantContext = Mockery::mock(TenantContext::class);

    $plan = new Plan(['limits' => ['requests_per_minute' => 120]]);
    $subscription = new Subscription;
    $subscription->setRelation('plan', $plan);

    $organization = Mockery::mock(Organization::class);
    $organization->shouldReceive('activeSubscription')->andReturn($subscription);

    $tenantContext->shouldReceive('getOrganization')->andReturn($organization);

    $service = new RateLimiter($limiter, $tenantContext);
    $limits = $service->getPlanRateLimit();

    expect($limits['max_attempts'])->toBe(120);
    expect($limits['description'])->toContain('120');
});

it('treats negative plan limits as unlimited', function (): void {
    $limiter = Mockery::mock(CacheRateLimiter::class);
    $tenantContext = Mockery::mock(TenantContext::class);

    $plan = new Plan(['limits' => ['requests_per_minute' => -1]]);
    $subscription = new Subscription;
    $subscription->setRelation('plan', $plan);

    $organization = Mockery::mock(Organization::class);
    $organization->shouldReceive('activeSubscription')->andReturn($subscription);

    $tenantContext->shouldReceive('getOrganization')->andReturn($organization);

    $service = new RateLimiter($limiter, $tenantContext);

    expect($service->getPlanRateLimit()['max_attempts'])->toBe(-1);
});

it('delegates limiter operations', function (): void {
    $limiter = Mockery::mock(CacheRateLimiter::class);
    $tenantContext = Mockery::mock(TenantContext::class);
    $tenantContext->shouldReceive('getOrganization')->andReturn(null);
    $tenantContext->shouldReceive('getUser')->andReturn(null);

    $service = new RateLimiter($limiter, $tenantContext);

    $limiter->shouldReceive('attempt')
        ->once()
        ->with('key', 5, Mockery::type(Closure::class), 120)
        ->andReturn('ok');

    $limiter->shouldReceive('tooManyAttempts')->once()->with('key', 5)->andReturn(true);
    $limiter->shouldReceive('hit')->once()->with('key', 120)->andReturn(3);
    $limiter->shouldReceive('attempts')->once()->with('key')->andReturn(2);
    $limiter->shouldReceive('retriesLeft')->once()->with('key', 5)->andReturn(4);
    $limiter->shouldReceive('clear')->once()->with('key');
    $limiter->shouldReceive('availableIn')->once()->with('key')->andReturn(15);

    $limits = ['max_attempts' => 5, 'decay_minutes' => 2, 'description' => ''];

    $result = $service->attempt('key', fn () => 'computed', $limits);
    expect($result)->toBe('ok');
    expect($service->tooManyAttempts('key', $limits))->toBeTrue();
    expect($service->hit('key', $limits))->toBe(3);
    expect($service->attempts('key'))->toBe(2);
    expect($service->retriesLeft('key', $limits))->toBe(4);
    $service->clear('key');
    expect($service->availableIn('key'))->toBe(15);
});

it('generates rate limit keys based on organization, user and suffix', function (): void {
    $limiter = Mockery::mock(CacheRateLimiter::class);
    $tenantContext = Mockery::mock(TenantContext::class);

    $organization = new Organization;
    $organization->setAttribute('id', 'org-1');

    $tenantContext->shouldReceive('getOrganization')->andReturn($organization);

    $service = new RateLimiter($limiter, $tenantContext);

    $user = new class
    {
        public string $id = 'user-1';
    };

    $request = Request::create('/api/resource', 'GET');
    $request->setUserResolver(fn () => $user);
    $request->server->set('REMOTE_ADDR', '203.0.113.10');

    $key = $service->generateKey($request, 'login');

    expect($key)->toBe('rate-limit:org-1:user-1:login');
});
