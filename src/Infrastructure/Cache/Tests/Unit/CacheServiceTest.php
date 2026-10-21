<?php

use Illuminate\Support\Facades\Cache;
use Infrastructure\Cache\CacheService;
use Tests\TestCase;

uses(TestCase::class);

afterEach(function (): void {
    Mockery::close();
});

it('remembers values with generated keys by default ttl', function (): void {
    config(['cache.prefix' => 'saas']);

    Cache::shouldReceive('remember')
        ->once()
        ->with('saas:dashboard', CacheService::DURATION_MEDIUM, Mockery::type(Closure::class))
        ->andReturn('cached-value');

    $result = CacheService::remember('dashboard', fn () => 'computed');

    expect($result)->toBe('cached-value');
});

it('uses specialized helpers to cache organization and user data', function (): void {
    config(['cache.prefix' => 'saas']);

    Cache::shouldReceive('remember')
        ->once()
        ->with('saas:organization:org-1', CacheService::DURATION_LONG, Mockery::type(Closure::class))
        ->andReturn('organization');

    Cache::shouldReceive('remember')
        ->once()
        ->with('saas:user:user-1', CacheService::DURATION_LONG, Mockery::type(Closure::class))
        ->andReturn('user');

    Cache::shouldReceive('remember')
        ->once()
        ->with('saas:plan:plan-1', CacheService::DURATION_DAY, Mockery::type(Closure::class))
        ->andReturn('plan');

    Cache::shouldReceive('remember')
        ->once()
        ->with('saas:subscription:org-1', CacheService::DURATION_MEDIUM, Mockery::type(Closure::class))
        ->andReturn('subscription');

    expect(CacheService::rememberOrganization('org-1', fn () => 'computed'))->toBe('organization');
    expect(CacheService::rememberUser('user-1', fn () => 'computed'))->toBe('user');
    expect(CacheService::rememberPlan('plan-1', fn () => 'computed'))->toBe('plan');
    expect(CacheService::rememberSubscription('org-1', fn () => 'computed'))->toBe('subscription');
});

it('remembers tagged cache when redis is the default store', function (): void {
    config(['cache.prefix' => 'saas', 'cache.default' => 'redis']);

    $taggedCache = Mockery::mock();
    $taggedCache->shouldReceive('remember')
        ->once()
        ->with('saas:reporting', CacheService::DURATION_MEDIUM, Mockery::type(Closure::class))
        ->andReturn('tagged');

    Cache::shouldReceive('tags')
        ->once()
        ->with(['reports'])
        ->andReturn($taggedCache);

    $result = CacheService::rememberWithTags(['reports'], 'reporting', fn () => 'computed');

    expect($result)->toBe('tagged');
});

it('falls back to remember when tags are not supported', function (): void {
    config(['cache.prefix' => 'saas', 'cache.default' => 'array']);

    Cache::shouldReceive('remember')
        ->once()
        ->with('saas:reporting', CacheService::DURATION_MEDIUM, Mockery::type(Closure::class))
        ->andReturn('fallback');

    $result = CacheService::rememberWithTags(['reports'], 'reporting', fn () => 'computed');

    expect($result)->toBe('fallback');
});

it('clears cached entities with prefixed keys', function (): void {
    config(['cache.prefix' => 'saas']);

    Cache::shouldReceive('forget')->once()->with('saas:organization:org-1');
    Cache::shouldReceive('forget')->twice()->with('saas:subscription:org-1');
    Cache::shouldReceive('forget')->once()->with('saas:user:user-1');
    Cache::shouldReceive('forget')->once()->with('saas:plan:plan-1');

    CacheService::clearOrganization('org-1');
    CacheService::clearUser('user-1');
    CacheService::clearPlan('plan-1');
    CacheService::clearSubscription('org-1');
});

it('clears cache tags only when redis is enabled', function (): void {
    config(['cache.default' => 'redis', 'cache.prefix' => 'saas']);

    $taggedCache = Mockery::mock();
    $taggedCache->shouldReceive('flush')->once();

    Cache::shouldReceive('tags')
        ->once()
        ->with('notifications')
        ->andReturn($taggedCache);

    CacheService::clearTag('notifications');
});

it('skips clearing tags for non redis drivers', function (): void {
    config(['cache.default' => 'array', 'cache.prefix' => 'saas']);

    Cache::shouldReceive('tags')->never();

    CacheService::clearTag('notifications');
});

it('forgets values stored with remember', function (): void {
    config(['cache.prefix' => 'saas']);

    Cache::shouldReceive('forget')->once()->with('saas:status-page:acme')->andReturn(true);

    expect(CacheService::forget('status-page:acme'))->toBeTrue();
});
