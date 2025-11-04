<?php

use App\Models\Organization;
use Features\Webhooks\Infrastructure\Eloquent\Models\Webhook;
use Features\Webhooks\Infrastructure\Eloquent\Models\WebhookDelivery;
use Features\Webhooks\Shared\Jobs\DispatchWebhookJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Http::fake();
});

it('skips dispatching when the webhook is inactive', function (): void {
    $webhook = Webhook::factory()
        ->inactive()
        ->withEvents(['user.created'])
        ->create();

    $job = new DispatchWebhookJob($webhook, 'user.created', ['foo' => 'bar']);
    $job->handle();

    expect(WebhookDelivery::count())->toBe(0);
});

it('skips dispatching when the webhook does not subscribe to the event', function (): void {
    $webhook = Webhook::factory()
        ->withEvents(['invoice.created'])
        ->create();

    $job = new DispatchWebhookJob($webhook, 'user.created', ['foo' => 'bar']);
    $job->handle();

    expect(WebhookDelivery::count())->toBe(0);
});

it('dispatches webhook deliveries for subscribed events', function (): void {
    $organization = Organization::factory()->create();
    $webhook = Webhook::factory()
        ->forOrganization($organization)
        ->withEvents(['user.created'])
        ->create([
            'timeout' => 10,
        ]);

    Http::fake([
        $webhook->url => Http::response('OK', 200),
    ]);

    $payload = ['foo' => 'bar'];

    $job = new DispatchWebhookJob($webhook->fresh(), 'user.created', $payload);
    $job->handle();

    $delivery = WebhookDelivery::query()->firstOrFail();

    expect($delivery->status)->toBe('success');
    expect($delivery->status_code)->toBe(200);
    expect($delivery->payload)->toBe($payload);
    expect($delivery->delivered_at)->not->toBeNull();
    expect($webhook->fresh()->last_triggered_at)->not->toBeNull();

    Http::assertSent(function ($request) use ($webhook, $payload) {
        return $request->url() === $webhook->url
            && $request['foo'] === $payload['foo']
            && $request->header('User-Agent')[0] === 'SaaS-Webhooks/1.0';
    });
});
