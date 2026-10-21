<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/test/client-ip', fn (Request $request) => response()->json(['ip' => $request->ip()]));
});

it('ignores X-Forwarded-For when no proxy is trusted', function () {
    config(['trustedproxy.proxies' => null]);

    $this->getJson('/test/client-ip', ['X-Forwarded-For' => '203.0.113.9'])
        ->assertJson(['ip' => '127.0.0.1']);
});

it('uses X-Forwarded-For from a trusted proxy', function () {
    config(['trustedproxy.proxies' => '10.0.0.0/8, 127.0.0.1']);

    $this->getJson('/test/client-ip', ['X-Forwarded-For' => '203.0.113.9'])
        ->assertJson(['ip' => '203.0.113.9']);
});

it('ignores X-Forwarded-For from an address that is not trusted', function () {
    config(['trustedproxy.proxies' => '10.0.0.5']);

    $this->getJson('/test/client-ip', ['X-Forwarded-For' => '203.0.113.9'])
        ->assertJson(['ip' => '127.0.0.1']);
});

it('trusts the connecting address with a wildcard', function () {
    config(['trustedproxy.proxies' => '*']);

    $this->getJson('/test/client-ip', ['X-Forwarded-For' => '203.0.113.9'])
        ->assertJson(['ip' => '203.0.113.9']);
});
