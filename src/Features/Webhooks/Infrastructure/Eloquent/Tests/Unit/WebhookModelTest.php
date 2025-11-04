<?php

use Features\Webhooks\Infrastructure\Eloquent\Models\Webhook;
use Features\Webhooks\Infrastructure\Eloquent\Models\WebhookDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('determines subscription support', function (): void {
    $webhook = Webhook::factory()->withEvents(['user.created'])->create();

    expect($webhook->isSubscribedTo('user.created'))->toBeTrue();
    expect($webhook->isSubscribedTo('invoice.created'))->toBeFalse();

    $wildcard = Webhook::factory()->allEvents()->create();
    expect($wildcard->isSubscribedTo('anything.here'))->toBeTrue();
});

it('provides scoped delivery relations', function (): void {
    $webhook = Webhook::factory()->create();

    $successful = WebhookDelivery::factory()->forWebhook($webhook)->successful()->create(['created_at' => now()->addMinutes(1)]);
    $failed = WebhookDelivery::factory()->forWebhook($webhook)->failed()->attempt(2)->create(['created_at' => now()->addMinutes(2)]);
    $pending = WebhookDelivery::factory()->forWebhook($webhook)->create(['created_at' => now()]);

    expect($webhook->successfulDeliveries()->pluck('id'))->toContain($successful->id);
    expect($webhook->failedDeliveries()->pluck('id'))->toContain($failed->id);
    expect($webhook->recentDeliveries(2)->pluck('id')->all())->toEqual([$failed->id, $successful->id]);
});

it('updates last triggered timestamp', function (): void {
    $webhook = Webhook::factory()->create(['last_triggered_at' => null]);

    $now = Carbon::parse('2025-01-01 10:00:00');
    Carbon::setTestNow($now);
    $webhook->updateLastTriggered();

    expect($webhook->fresh()->last_triggered_at)->toEqual($now);
    Carbon::setTestNow();
});
