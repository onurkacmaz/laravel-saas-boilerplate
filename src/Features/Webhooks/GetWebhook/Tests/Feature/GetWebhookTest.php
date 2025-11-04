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

it('shows webhook details with status metadata', function (): void {
    $webhook = Webhook::factory()->forOrganization($this->organization)->create([
        'name' => 'My Webhook',
        'events' => ['user.created'],
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->getJson("/api/v1/webhooks/{$webhook->id}");

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Webhook fetched successfully')
        ->assertJsonPath('data.id', $webhook->id)
        ->assertJsonPath('data.name', 'My Webhook')
        ->assertJsonPath('data.events.0', 'user.created');
});
