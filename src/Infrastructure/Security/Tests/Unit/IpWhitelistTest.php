<?php

use Illuminate\Http\Request;
use Infrastructure\Security\Middleware\IpWhitelist;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

uses(TestCase::class);

it('allows all traffic when no whitelist is supplied', function (): void {
    $middleware = new IpWhitelist;
    $request = Request::create('/api/ping', 'GET', [], [], [], ['REMOTE_ADDR' => '192.0.2.1']);

    $response = $middleware->handle($request, fn () => new Response('next'));

    expect($response->getContent())->toBe('next');
});

it('allows requests from whitelisted ip addresses', function (): void {
    $middleware = new IpWhitelist;
    $request = Request::create('/api/ping', 'GET', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);

    $response = $middleware->handle($request, fn () => new Response('ok'), '10.0.0.1', '127.0.0.1');

    expect($response->getContent())->toBe('ok');
});

it('rejects requests from non whitelisted ip addresses', function (): void {
    $middleware = new IpWhitelist;
    $request = Request::create('/api/ping', 'GET', [], [], [], ['REMOTE_ADDR' => '192.0.2.1']);

    $response = $middleware->handle($request, fn () => new Response('should not run'), '10.0.0.1');

    expect($response->getStatusCode())->toBe(403);
    expect($response->getContent())->toContain('Access denied');
});
