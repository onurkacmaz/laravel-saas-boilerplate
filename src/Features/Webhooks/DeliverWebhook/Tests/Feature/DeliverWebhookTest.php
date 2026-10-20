<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Features\Webhooks\DeliverWebhook\Application\DeliverWebhook;
use Features\Webhooks\DeliverWebhook\Application\DeliverWebhookHandler;
use Features\Webhooks\Infrastructure\Eloquent\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create([
        'owner_id' => $this->user->id,
    ]);

    $this->role = Role::factory()->create([
        'organization_id' => $this->organization->id,
        'slug' => 'owner',
    ]);

    $this->user->organizations()->attach($this->organization->id, [
        'role_id' => $this->role->id,
    ]);
});

describe('Webhook Delivery', function (): void {
    it('records successful deliveries', function (): void {
        Http::fake([
            'https://example.com/webhook' => Http::response(['success' => true], 200),
        ]);

        $webhook = Webhook::factory()->forOrganization($this->organization)->create([
            'url' => 'https://example.com/webhook',
            'events' => ['user.created'],
        ]);

        $payload = [
            'event' => 'user.created',
            'data' => ['id' => 1, 'name' => 'Test User'],
        ];

        $handler = new DeliverWebhookHandler;
        $handler(
            command: new DeliverWebhook(
                webhook: $webhook,
                eventName: 'user.created',
                payload: $payload
            )
        );

        $delivery = $webhook->deliveries()->first();

        expect($delivery)->not()->toBeNull();
        expect($delivery->status)->toBe('success');
        expect($delivery->status_code)->toBe(200);
    });

    it('stores failure details when endpoint responds with an error', function (): void {
        Http::fake([
            'https://example.com/webhook' => Http::response(['error' => 'Bad Request'], 400),
        ]);

        $webhook = Webhook::factory()->forOrganization($this->organization)->create([
            'url' => 'https://example.com/webhook',
            'events' => ['user.created'],
        ]);

        $handler = new DeliverWebhookHandler;
        $handler(
            command: new DeliverWebhook(
                webhook: $webhook,
                eventName: 'user.created',
                payload: ['data' => 'test'],
            )
        );

        $delivery = $webhook->deliveries()->first();

        expect($delivery)->not()->toBeNull();
        expect($delivery->status)->toBe('failed');
        expect($delivery->status_code)->toBe(400);
        expect($delivery->response_body)->toContain('Bad Request');
    });

    it('includes HMAC signature header when secret is configured', function (): void {
        Http::fake();

        $webhook = Webhook::factory()->forOrganization($this->organization)->create([
            'url' => 'https://example.com/webhook',
            'secret' => 'my-secret-key',
            'events' => ['user.created'],
        ]);

        $payload = ['event' => 'user.created'];

        $handler = new DeliverWebhookHandler;
        $handler(
            command: new DeliverWebhook(
                webhook: $webhook,
                eventName: 'user.created',
                payload: $payload
            )
        );

        Http::assertSent(function ($request) {
            return $request->hasHeader('X-Webhook-Signature');
        });
    });
});
