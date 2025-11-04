<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Features\Webhooks\Infrastructure\Eloquent\Models\Webhook;
use Features\Webhooks\Infrastructure\Eloquent\Models\WebhookDelivery;
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

it('lists webhook deliveries with pagination metadata', function (): void {
    $webhook = Webhook::factory()->forOrganization($this->organization)->create();
    WebhookDelivery::factory()->count(5)->forWebhook($webhook)->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->getJson("/api/v1/webhooks/{$webhook->id}/deliveries");

    $response->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('meta.total', 5);
});
