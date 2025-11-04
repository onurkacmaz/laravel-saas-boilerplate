<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Features\Webhooks\Infrastructure\Eloquent\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

it('creates a webhook and returns resource payload', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->postJson('/api/v1/webhooks', [
            'name' => 'Test Webhook',
            'url' => 'https://example.com/webhook',
            'events' => ['user.created', 'user.updated'],
        ]);

    $response->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Webhook created successfully')
        ->assertJsonPath('data.name', 'Test Webhook')
        ->assertJsonPath('data.url', 'https://example.com/webhook')
        ->assertJsonPath('data.events.0', 'user.created');

    expect($response->json('data.secret'))->not()->toBeNull();
    expect(strlen($response->json('data.secret')))->toBeGreaterThanOrEqual(32);

    expect(Webhook::where('organization_id', $this->organization->id)->count())->toBe(1);
});

it('validates webhook creation payload', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->postJson('/api/v1/webhooks', [
            'name' => '',
            'url' => 'not-a-valid-url',
            'events' => [],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'url', 'events']);
});
