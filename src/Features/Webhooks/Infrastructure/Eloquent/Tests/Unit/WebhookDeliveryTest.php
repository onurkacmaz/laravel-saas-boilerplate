<?php

use Features\Webhooks\Infrastructure\Eloquent\Models\Webhook;
use Features\Webhooks\Infrastructure\Eloquent\Models\WebhookDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('marks deliveries as successful', function (): void {
    $delivery = WebhookDelivery::factory()->create(['status' => 'pending']);

    $delivery->markAsSuccess(200, '{"ok":true}');

    $delivery->refresh();

    expect($delivery->status)->toBe('success');
    expect($delivery->status_code)->toBe(200);
    expect($delivery->response_body)->toBe('{"ok":true}');
    expect($delivery->delivered_at)->not->toBeNull();
    expect($delivery->isSuccess())->toBeTrue();
    expect($delivery->isFailed())->toBeFalse();
    expect($delivery->isPending())->toBeFalse();
});

it('marks deliveries as failed', function (): void {
    $delivery = WebhookDelivery::factory()->create(['status' => 'pending']);

    $delivery->markAsFailed(500, 'Timeout');

    $delivery->refresh();

    expect($delivery->status)->toBe('failed');
    expect($delivery->status_code)->toBe(500);
    expect($delivery->error_message)->toBe('Timeout');
    expect($delivery->isFailed())->toBeTrue();
});

it('increments attempts and evaluates retry eligibility', function (): void {
    $webhook = Webhook::factory()->create(['retry_limit' => 3]);
    $delivery = WebhookDelivery::factory()
        ->forWebhook($webhook)
        ->failed()
        ->attempt(2)
        ->create(['status' => 'failed']);

    $delivery->incrementAttempt();
    $delivery->refresh();

    expect($delivery->attempt)->toBe(3);
    expect($delivery->canRetry())->toBeFalse();

    $delivery->update(['attempt' => 1]);
    expect($delivery->canRetry())->toBeTrue();
});
