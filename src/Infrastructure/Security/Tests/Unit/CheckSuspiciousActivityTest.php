<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Infrastructure\Security\Middleware\CheckSuspiciousActivity;
use Tests\TestCase;

uses(TestCase::class);

afterEach(function (): void {
    \Mockery::close();
});

it('rejects requests containing suspicious patterns and records activity', function (): void {
    Cache::shouldReceive('has')->never();

    Cache::shouldReceive('increment')
        ->once()
        ->with('suspicious_activity:127.0.0.1')
        ->andReturn(1);

    Cache::shouldReceive('put')
        ->once()
        ->with(
            'suspicious_activity:127.0.0.1',
            1,
            \Mockery::on(fn ($value) => $value instanceof DateTimeInterface)
        );

    Log::shouldReceive('warning')->once();

    $middleware = new CheckSuspiciousActivity;
    $request = Request::create('/api/ping', 'POST', ['payload' => '<script>alert(1)</script>']);

    $response = $middleware->handle($request, function (): never {
        throw new RuntimeException('Middleware should have halted execution.');
    });

    expect($response->getStatusCode())->toBe(400);
});

it('blocks known malicious ip addresses', function (): void {
    Cache::shouldReceive('has')
        ->once()
        ->with('blocked_ip:127.0.0.1')
        ->andReturn(true);

    Log::shouldReceive('warning')->once();

    $middleware = new CheckSuspiciousActivity;
    $request = Request::create('/api/ping', 'GET');

    $response = $middleware->handle($request, function (): never {
        throw new RuntimeException('Blocked IP should not reach the next middleware.');
    });

    expect($response->getStatusCode())->toBe(403);
});

it('throttles unusual request rates', function (): void {
    Cache::shouldReceive('has')
        ->once()
        ->with('blocked_ip:127.0.0.1')
        ->andReturn(false);

    Cache::shouldReceive('add')
        ->once()
        ->with(
            'request_rate:127.0.0.1',
            0,
            \Mockery::on(fn ($value) => $value instanceof DateTimeInterface)
        )
        ->andReturn(false);

    Cache::shouldReceive('increment')
        ->once()
        ->with('request_rate:127.0.0.1')
        ->andReturn(102);

    Cache::shouldReceive('increment')
        ->once()
        ->with('suspicious_activity:127.0.0.1')
        ->andReturn(2);

    Log::shouldReceive('warning')->once();

    $middleware = new CheckSuspiciousActivity;
    $request = Request::create('/api/ping', 'GET');

    $response = $middleware->handle($request, function (): never {
        throw new RuntimeException('Rate limited IP should not reach the next middleware.');
    });

    expect($response->getStatusCode())->toBe(429);
});

it('blocks ip addresses after repeated suspicious activity', function (): void {
    Cache::shouldReceive('increment')
        ->once()
        ->with('suspicious_activity:127.0.0.1')
        ->andReturn(5);

    Cache::shouldReceive('put')
        ->once()
        ->with(
            'blocked_ip:127.0.0.1',
            true,
            \Mockery::on(fn ($value) => $value instanceof DateTimeInterface)
        );

    Log::shouldReceive('warning')->twice();

    $middleware = new CheckSuspiciousActivity;
    $request = Request::create('/api/ping', 'POST', ['payload' => '<script>alert(1)</script>']);

    $response = $middleware->handle($request, function (): never {
        throw new RuntimeException('Blocked IP should not proceed.');
    });

    expect($response->getStatusCode())->toBe(400);
});
